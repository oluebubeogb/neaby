<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Jobs\ImportProductsJob;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Services\Seller\ProductImporter;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $seo = Seo::make('Bulk import', index: false);

        return Inertia::render('Sell/Import/Index', [
            'batches' => ImportBatch::query()->where('store_id', $store->id)->latest()->limit(20)->get()->map(fn ($b) => $this->batch($b)),
            'categoryOptions' => Category::query()->active()->get(['name', 'slug']),
            'maxRows' => ProductImporter::MAX_ROWS,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request, ProductImporter $importer)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx']]);
        $batch = $importer->stage($request->user(), $store, $request->file('file'));
        ImportProductsJob::dispatch($batch->id); // sync queue → runs inline

        return redirect()->route('sell.import.show', $batch);
    }

    public function show(Request $request, ImportBatch $batch)
    {
        abort_unless($batch->user_id === $request->user()->id, 404);
        $seo = Seo::make('Import report', index: false);

        return Inertia::render('Sell/Import/Show', [
            'batch' => $this->batch($batch),
            'rows' => $batch->rows()->orderBy('row_number')->limit(500)->get()->map(fn ($r) => [
                'row' => $r->row_number, 'status' => $r->status, 'title' => $r->data['title'] ?? null,
                'errors' => $r->errors ?? [], 'product_id' => $r->product_id,
            ]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function template()
    {
        return response(ProductImporter::templateCsv(), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="neaby-import-template.csv"']);
    }

    public function errors(Request $request, ImportBatch $batch)
    {
        abort_unless($batch->user_id === $request->user()->id, 404);
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['row', ...ProductImporter::FIELDS, 'errors']);
        foreach ($batch->rows()->where('status', 'failed')->orderBy('row_number')->cursor() as $r) {
            fputcsv($out, [$r->row_number, ...array_map(fn ($f) => $r->data[$f] ?? '', ProductImporter::FIELDS), implode(' | ', $r->errors ?? [])]);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="neaby-import-'.$batch->id.'-errors.csv"']);
    }

    private function batch(ImportBatch $b): array
    {
        return [
            'id' => $b->id, 'filename' => $b->filename, 'status' => $b->status,
            'total' => $b->total_rows, 'imported' => $b->imported_rows, 'failed' => $b->failed_rows,
            'error' => $b->error, 'created_at' => $b->created_at?->format('j M Y, g:ia'),
        ];
    }
}
