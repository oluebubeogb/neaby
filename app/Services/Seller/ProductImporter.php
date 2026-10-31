<?php

namespace App\Services\Seller;

use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\NigeriaStates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Bulk CSV/XLSX import. The upload is parsed into import_rows straight away
 * (so nothing depends on the file afterwards), then ImportProductsJob validates
 * and creates/updates products row by row with a per-row error report.
 * Imported listings are saved as drafts — sellers add photos, then publish.
 */
class ProductImporter
{
    public const MAX_ROWS = 1000;

    public const FIELDS = ['id', 'title', 'description', 'price', 'compare_at', 'category', 'condition', 'quantity', 'city', 'state', 'delivery_fee', 'pickup'];

    /** Header aliases → canonical field. */
    private const ALIASES = [
        'id' => ['id', 'product_id', 'listing_id'],
        'title' => ['title', 'name', 'product', 'product_name', 'item'],
        'description' => ['description', 'details', 'desc', 'about'],
        'price' => ['price', 'price_ngn', 'amount', 'selling_price', 'naira'],
        'compare_at' => ['compare_at', 'old_price', 'was', 'original_price'],
        'category' => ['category', 'category_slug', 'type'],
        'condition' => ['condition', 'state_of_item', 'quality'],
        'quantity' => ['quantity', 'qty', 'stock', 'units'],
        'city' => ['city', 'town', 'location'],
        'state' => ['state', 'region'],
        'delivery_fee' => ['delivery_fee', 'delivery', 'shipping'],
        'pickup' => ['pickup', 'pickup_available'],
    ];

    public function stage(User $user, Store $store, UploadedFile $file): ImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $reader = $ext === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($file->getRealPath());

        $headers = null;
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($v) => is_string($v) ? trim($v) : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v), $row->toArray());
                if ($headers === null) {
                    $headers = array_map(fn ($h) => Str::of((string) $h)->lower()->snake()->replace(['(', ')', '₦'], '')->trim('_')->toString(), $cells);
                    continue;
                }
                if (! array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    $reader->close();
                    throw ValidationException::withMessages(['file' => 'Up to '.self::MAX_ROWS.' rows per import — split the file and try again.']);
                }
                $rows[] = $cells;
            }
            break; // first sheet only
        }
        $reader->close();

        if (! $headers || ! $rows) {
            throw ValidationException::withMessages(['file' => 'That file has no rows. Use the template — the first row must be the column headers.']);
        }
        $mapping = $this->map($headers);
        if (! isset($mapping['price']) || (! isset($mapping['title']) && ! isset($mapping['id']))) {
            throw ValidationException::withMessages(['file' => 'We need at least “title” and “price” columns (or “id” to update existing listings).']);
        }

        return DB::transaction(function () use ($user, $store, $file, $headers, $mapping, $rows) {
            $batch = ImportBatch::create([
                'user_id' => $user->id, 'store_id' => $store->id,
                'filename' => Str::limit($file->getClientOriginalName(), 190, ''),
                'headers' => $headers, 'mapping' => $mapping, 'status' => 'queued', 'total_rows' => count($rows),
            ]);
            foreach (array_chunk($rows, 200, true) as $chunk) {
                $insert = [];
                foreach ($chunk as $i => $cells) {
                    $data = [];
                    foreach ($mapping as $field => $col) {
                        $data[$field] = $cells[$col] ?? null;
                    }
                    $insert[] = ['import_batch_id' => $batch->id, 'row_number' => $i + 2, 'data' => json_encode($data), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()];
                }
                ImportRow::insert($insert);
            }

            return $batch;
        });
    }

    public function map(array $headers): array
    {
        $mapping = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($headers as $i => $h) {
                if (in_array($h, $aliases, true)) {
                    $mapping[$field] = $i;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function process(ImportBatch $batch): void
    {
        $batch->forceFill(['status' => 'processing', 'started_at' => now()])->save();
        $categories = Category::query()->active()->get(['id', 'name', 'slug']);
        $catIndex = [];
        foreach ($categories as $c) {
            $catIndex[Str::lower($c->slug)] = $c->id;
            $catIndex[Str::lower($c->name)] = $c->id;
        }

        $ok = 0;
        $failed = 0;
        $batch->rows()->where('status', 'pending')->orderBy('row_number')->chunkById(100, function ($rows) use ($batch, $catIndex, &$ok, &$failed) {
            foreach ($rows as $row) {
                try {
                    $product = $this->importRow($batch, $row->data, $catIndex);
                    $row->forceFill(['status' => 'imported', 'product_id' => $product->id, 'errors' => null])->save();
                    $ok++;
                } catch (ValidationException $e) {
                    $row->forceFill(['status' => 'failed', 'errors' => collect($e->errors())->flatten()->values()->all()])->save();
                    $failed++;
                }
            }
        });

        $batch->forceFill([
            'status' => 'completed', 'finished_at' => now(),
            'imported_rows' => $batch->rows()->where('status', 'imported')->count(),
            'failed_rows' => $batch->rows()->where('status', 'failed')->count(),
        ])->save();
    }

    private function importRow(ImportBatch $batch, array $raw, array $catIndex): Product
    {
        $num = fn ($v) => $v === null || $v === '' ? null : (is_numeric($v) ? $v : (is_numeric($c = preg_replace('/[^\d.]/', '', (string) $v)) ? $c : $v));
        $bool = fn ($v) => $v === null || $v === '' ? null : in_array(Str::lower((string) $v), ['1', 'yes', 'y', 'true'], true);
        $cond = Str::lower(trim((string) ($raw['condition'] ?? '')));
        $cond = match ($cond) {
            '', 'new', 'brand new', 'brand_new' => 'new',
            'used', 'fairly used', 'fairly_used', 'uk used', 'tokunbo' => 'used',
            'refurbished', 'refurb' => 'refurbished',
            default => $cond,
        };
        $catKey = Str::lower(trim((string) ($raw['category'] ?? '')));
        $data = [
            'id' => $num($raw['id'] ?? null),
            'title' => isset($raw['title']) ? trim((string) $raw['title']) : null,
            'description' => isset($raw['description']) ? trim((string) $raw['description']) : null,
            'price' => $num($raw['price'] ?? null),
            'compare_at' => $num($raw['compare_at'] ?? null),
            'category_id' => $catKey === '' ? null : ($catIndex[$catKey] ?? $catIndex[Str::slug($catKey)] ?? 'unknown'),
            'condition' => $cond,
            'quantity' => $num($raw['quantity'] ?? null) ?? 1,
            'city' => isset($raw['city']) ? trim((string) $raw['city']) : null,
            'state' => isset($raw['state']) && $raw['state'] !== '' ? NigeriaStates::normalize((string) $raw['state']) : null,
            'delivery_fee' => $num($raw['delivery_fee'] ?? null),
            'pickup' => $bool($raw['pickup'] ?? null),
        ];

        $existing = null;
        if ($data['id']) {
            $existing = Product::query()->where('store_id', $batch->store_id)->find((int) $data['id']);
            if (! $existing) {
                throw ValidationException::withMessages(['id' => "No listing #{$data['id']} in your store."]);
            }
        }

        $v = Validator::make($data, [
            'title' => [$existing ? 'nullable' : 'required', 'string', 'min:3', 'max:140'],
            'description' => [$existing ? 'nullable' : 'required', 'string', 'min:10', 'max:5000'],
            'price' => [$existing ? 'nullable' : 'required', 'numeric', 'min:1', 'max:1000000000'],
            'compare_at' => ['nullable', 'numeric', 'max:1000000000'],
            'category_id' => [$existing ? 'nullable' : 'required', 'integer'],
            'condition' => ['required', Rule::in(Product::CONDITIONS)],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', Rule::in(NigeriaStates::all())],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ], [
            'category_id.integer' => 'Unknown category “'.($raw['category'] ?? '').'”. Use a category name or slug from the template.',
            'category_id.required' => 'Category is required.',
            'condition.in' => 'Condition must be new, used or refurbished.',
            'state.in' => 'Unknown Nigerian state.',
        ]);
        $v->validate();
        if ($data['compare_at'] && $data['price'] && $data['compare_at'] <= $data['price']) {
            throw ValidationException::withMessages(['compare_at' => 'compare_at must be higher than price.']);
        }

        $p = $existing ?? new Product([
            'store_id' => $batch->store_id, 'user_id' => $batch->user_id,
            'currency' => config('neaby.currency', 'NGN'), 'status' => 'draft',
        ]);
        if (! $existing) {
            $p->store_id = $batch->store_id;
            $p->user_id = $batch->user_id;
            $p->status = 'draft';
        }
        foreach (['title' => 'title', 'description' => 'description', 'category_id' => 'category_id', 'condition' => 'condition', 'quantity' => 'quantity', 'city' => 'city', 'state' => 'state'] as $k => $col) {
            if ($data[$k] !== null && $data[$k] !== '') {
                $p->{$col} = $data[$k];
            }
        }
        if ($data['price'] !== null) {
            $p->price_minor = (int) round($data['price'] * 100);
        }
        if ($data['compare_at'] !== null) {
            $p->compare_at_minor = (int) round($data['compare_at'] * 100);
        }
        if ($data['delivery_fee'] !== null) {
            $p->delivery_fee_minor = (int) round($data['delivery_fee'] * 100);
        }
        if ($data['pickup'] !== null) {
            $p->pickup_available = $data['pickup'];
        }
        if (! $p->exists) {
            $p->slug = Product::uniqueSlug($p->title);
        }
        if ($p->isDirty('quantity')) {
            $p->stock_updated_at = now();
        }
        if ($existing && $p->quantity < $p->reserved_quantity) {
            throw ValidationException::withMessages(['quantity' => "{$p->reserved_quantity} unit(s) are reserved by open orders — quantity can’t go below that."]);
        }
        $p->save();

        return $p;
    }

    public static function templateCsv(): string
    {
        $rows = [
            ['title', 'description', 'price', 'compare_at', 'category', 'condition', 'quantity', 'city', 'state', 'delivery_fee', 'pickup'],
            ['Ankara midi dress', 'Handmade ankara midi dress, sizes 10-14. Lined, side zip.', '15000', '', 'fashion', 'new', '5', 'Ikeja', 'Lagos', '1500', 'yes'],
            ['Fairly used iPhone 12 128GB', 'Neat iPhone 12, battery health 87%, comes with charger.', '420000', '', 'phones-tablets', 'used', '1', 'Wuse', 'FCT', '', 'yes'],
        ];
        $out = fopen('php://temp', 'w+');
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
        rewind($out);

        return stream_get_contents($out);
    }
}
