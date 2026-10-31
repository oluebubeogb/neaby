<?php

namespace App\Services\Resale;

use App\Models\AuditLog;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ResellerListing;
use App\Models\SupplierRelationship;
use App\Models\SupplierRequest;
use App\Models\User;
use App\Notifications\NeabyNotice;
use App\Services\Commerce\Inventory;
use App\Services\Membership\Entitlements;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resale + Supplier Connect (blueprint 13–16, 22).
 *  - A reseller offer always points at the ORIGINAL supplier product; it has its
 *    own price but no stock of its own — availability is read from the original,
 *    so inventory is shared and never multiplied.
 *  - Only original products can be resold: an offer can never be the source of
 *    another offer (there is no code path that turns a ResellerListing into a Product).
 *  - Accepted supplier requests are commitments: they keep working after either
 *    side's membership lapses.
 */
class ResaleService
{
    public function __construct(private readonly Entitlements $entitlements, private readonly Inventory $inventory) {}

    public function eligibleProduct(Product $product, User $reseller): void
    {
        $product->loadMissing(['store', 'owner']);
        if ($product->user_id === $reseller->id) {
            throw ValidationException::withMessages(['product' => 'You can’t resell your own listing — sell it directly.']);
        }
        if (! $product->resale_enabled || $product->resale_price_minor === null || ! $product->isPublic()) {
            throw ValidationException::withMessages(['product' => 'This product isn’t open for resale.']);
        }
        if (! $this->entitlements->supplierProgrammeLive($product)) {
            throw ValidationException::withMessages(['product' => 'This supplier’s resale programme is paused.']);
        }
        $blocked = SupplierRelationship::query()->where('original_supplier_id', $product->user_id)
            ->where('reseller_id', $reseller->id)->where('status', 'blocked')->exists();
        if ($blocked) {
            throw ValidationException::withMessages(['product' => 'This supplier isn’t accepting resale from your account.']);
        }
    }

    public function requireReseller(User $user): void
    {
        if (! $this->entitlements->has($user, 'reseller.access')) {
            throw ValidationException::withMessages(['membership' => 'Reseller Access is required for this.']);
        }
    }

    public function saveOffer(User $reseller, Product $product, array $data, ?ResellerListing $listing = null): ResellerListing
    {
        $this->requireReseller($reseller);
        $this->eligibleProduct($product, $reseller);

        $price = (int) $data['selling_price_minor'];
        if ($product->resale_min_price_minor && $price < $product->resale_min_price_minor) {
            throw ValidationException::withMessages(['selling_price' => 'The supplier’s minimum resale price is ₦'.number_format($product->resale_min_price_minor / 100).'.']);
        }
        if (! $listing && ResellerListing::query()->where('reseller_id', $reseller->id)->count() >= config('neaby.resale.max_offers_per_reseller', 200)) {
            throw ValidationException::withMessages(['product' => 'You’ve reached the maximum number of offers.']);
        }

        $listing ??= ResellerListing::query()->firstOrNew(['reseller_id' => $reseller->id, 'original_product_id' => $product->id]);
        $listing->fill([
            'original_supplier_id' => $product->user_id, // always the true source
            'selling_price_minor' => $price,
            'extra_costs_minor' => (int) ($data['extra_costs_minor'] ?? 0),
            'custom_title' => $data['custom_title'] ?? null,
            'custom_description' => $data['custom_description'] ?? null,
            'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
            'status' => $data['status'] ?? 'active',
            'inactive_reason' => null,
        ]);
        if (! $listing->exists) {
            $listing->slug = ResellerListing::newSlug();
        }
        $listing->save();

        SupplierRelationship::query()->firstOrCreate(['original_supplier_id' => $product->user_id, 'reseller_id' => $reseller->id], ['status' => 'active']);

        return $listing;
    }

    /** Is this public offer page live right now? */
    public function offerLive(ResellerListing $l): bool
    {
        $l->loadMissing(['product.store', 'product.owner', 'reseller']);
        if ($l->status !== 'active' || ! $l->product || ! $l->product->isPublic() || ! $l->product->resale_enabled) {
            return false;
        }

        return $this->entitlements->hasWithGrace($l->reseller, 'reseller.access')
            && $this->entitlements->supplierProgrammeLive($l->product);
    }

    // ── Supplier Connect ─────────────────────────────────────────────

    public function sendRequest(User $reseller, Product $product, array $data): SupplierRequest
    {
        $this->requireReseller($reseller);
        $this->eligibleProduct($product, $reseller);
        if (! in_array($data['fulfilment_method'], $this->allowedFulfilment($product), true)) {
            throw ValidationException::withMessages(['fulfilment_method' => 'This supplier doesn’t offer that fulfilment option.']);
        }
        $open = SupplierRequest::query()->where('requesting_reseller_id', $reseller->id)
            ->where('original_product_id', $product->id)->whereIn('status', SupplierRequest::OPEN)->exists();
        if ($open) {
            throw ValidationException::withMessages(['product' => 'You already have an open request for this product.']);
        }

        $req = SupplierRequest::create([
            'original_product_id' => $product->id,
            'original_supplier_id' => $product->user_id,
            'requesting_reseller_id' => $reseller->id,
            'reseller_listing_id' => ResellerListing::query()->where('reseller_id', $reseller->id)->where('original_product_id', $product->id)->value('id'),
            'requested_quantity' => (int) $data['requested_quantity'],
            'request_type' => $data['request_type'],
            'fulfilment_method' => $data['fulfilment_method'],
            'message' => $data['message'] ?? null,
            'status' => 'sent',
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        Notify::send($product->owner, new NeabyNotice('New supplier request', "@{$reseller->username} asked about “{$product->title}” ({$req->requested_quantity} unit(s), ".str_replace('_', ' ', $req->fulfilment_method).').', route('requests.show', $req), 'resale'));

        return $req;
    }

    public function allowedFulfilment(Product $product): array
    {
        return match ($product->resale_fulfilment) {
            'reseller_collects' => ['reseller_collects'],
            'supplier_dispatches' => ['supplier_dispatches'],
            default => SupplierRequest::FULFILMENT,
        };
    }

    /** Supplier-side transitions. */
    public function supplierRespond(SupplierRequest $req, User $supplier, string $action, array $data = []): void
    {
        if ($req->original_supplier_id !== $supplier->id) {
            abort(403);
        }
        DB::transaction(function () use ($req, $action, $data, $supplier) {
            $req = SupplierRequest::query()->lockForUpdate()->findOrFail($req->id);
            $open = in_array($req->status, SupplierRequest::OPEN, true);
            match ($action) {
                'confirm' => $open ? $req->forceFill(['status' => 'availability_confirmed', 'supplier_response' => $data['response'] ?? null, 'responded_at' => now()])->save() : $this->bad(),
                'decline' => $open ? $req->forceFill(['status' => 'availability_declined', 'supplier_response' => $data['response'] ?? null, 'responded_at' => now()])->save() : $this->bad(),
                'propose' => $open ? $req->forceFill(['status' => 'terms_proposed', 'supplier_response' => $data['response'] ?? null, 'proposed_unit_price_minor' => $data['proposed_unit_price_minor'] ?? null, 'responded_at' => now()])->save() : $this->bad(),
                'fulfil' => $req->status === 'accepted' ? $this->fulfil($req) : $this->bad(),
                'cancel' => $this->cancel($req, $supplier),
                default => $this->bad(),
            };
            $this->notifyOther($req->fresh(), $supplier);
        });
    }

    /** Reseller-side transitions. Accept/cancel never re-check membership (commitment). */
    public function resellerRespond(SupplierRequest $req, User $reseller, string $action): void
    {
        if ($req->requesting_reseller_id !== $reseller->id) {
            abort(403);
        }
        DB::transaction(function () use ($req, $action, $reseller) {
            $req = SupplierRequest::query()->lockForUpdate()->findOrFail($req->id);
            match ($action) {
                'accept' => in_array($req->status, ['availability_confirmed', 'terms_proposed'], true) ? $this->accept($req) : $this->bad(),
                'cancel' => $this->cancel($req, $reseller),
                default => $this->bad(),
            };
            $this->notifyOther($req->fresh(), $reseller);
        });
    }

    private function accept(SupplierRequest $req): void
    {
        $product = Product::query()->findOrFail($req->original_product_id);
        $hold = $this->inventory->reserve($product, $req->requested_quantity, $req, null);
        if (! $hold) {
            throw ValidationException::withMessages(['status' => "Only {$product->available()} unit(s) are available now — ask the supplier to update stock."]);
        }
        $req->forceFill(['status' => 'accepted', 'accepted_at' => now(), 'stock_reserved' => true])->save();
        AuditLog::create(['actor_id' => $req->requesting_reseller_id, 'action' => 'supplier_request.accepted', 'subject_type' => SupplierRequest::class, 'subject_id' => $req->id, 'meta' => ['qty' => $req->requested_quantity]]);
    }

    private function fulfil(SupplierRequest $req): void
    {
        $this->holds($req)->each(fn ($r) => $this->inventory->consume($r));
        $req->forceFill(['status' => 'fulfilled', 'fulfilled_at' => now(), 'stock_reserved' => false])->save();
    }

    private function cancel(SupplierRequest $req, User $actor): void
    {
        if (in_array($req->status, ['fulfilled', 'cancelled', 'expired', 'availability_declined'], true)) {
            $this->bad();
        }
        $this->holds($req)->each(fn ($r) => $this->inventory->release($r));
        $req->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'stock_reserved' => false])->save();
        AuditLog::create(['actor_id' => $actor->id, 'action' => 'supplier_request.cancelled', 'subject_type' => SupplierRequest::class, 'subject_id' => $req->id]);
    }

    private function holds(SupplierRequest $req)
    {
        return InventoryReservation::query()->where('holder_type', $req->getMorphClass())->where('holder_id', $req->id)->where('status', 'active')->get();
    }

    private function notifyOther(SupplierRequest $req, User $actor): void
    {
        $other = $actor->id === $req->original_supplier_id ? $req->reseller : $req->supplier;
        $label = str_replace('_', ' ', $req->status);
        Notify::send($other, new NeabyNotice("Supplier request: {$label}", "“{$req->product?->title}” · {$req->requested_quantity} unit(s).", route('requests.show', $req), 'resale', mail: in_array($req->status, ['accepted', 'terms_proposed', 'availability_confirmed'], true)));
    }

    private function bad(): never
    {
        throw ValidationException::withMessages(['status' => 'That action isn’t available for this request.']);
    }

    /** Open requests with no reply expire (never accepted ones). */
    public function expireStale(): int
    {
        return SupplierRequest::query()->whereIn('status', SupplierRequest::OPEN)
            ->where('created_at', '<=', now()->subDays(config('neaby.resale.request_expiry_days', 7)))
            ->update(['status' => 'expired']);
    }
}
