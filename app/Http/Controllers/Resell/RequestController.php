<?php

namespace App\Http\Controllers\Resell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SupplierRequest;
use App\Services\Resale\ResaleService;
use App\Support\PresentCommerce;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Supplier Connect requests — incoming (as supplier) and outgoing (as reseller). */
class RequestController extends Controller
{
    public function __construct(private readonly ResaleService $resale) {}

    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $tab = $request->query('tab') === 'outgoing' ? 'outgoing' : 'incoming';
        $q = SupplierRequest::query()->with(['product.cover', 'supplier', 'reseller'])
            ->where($tab === 'incoming' ? 'original_supplier_id' : 'requesting_reseller_id', $uid)->latest()->paginate(30)->withQueryString();
        $seo = Seo::make('Supplier requests', index: false);

        return Inertia::render('Requests/Index', [
            'tab' => $tab,
            'requests' => collect($q->items())->map(fn ($r) => PresentCommerce::supplierRequest($r, $uid))->values(),
            'counts' => [
                'incoming' => SupplierRequest::query()->where('original_supplier_id', $uid)->whereIn('status', SupplierRequest::OPEN)->count(),
                'outgoing' => SupplierRequest::query()->where('requesting_reseller_id', $uid)->whereIn('status', [...SupplierRequest::OPEN, 'accepted'])->count(),
            ],
            'next_page_url' => $q->nextPageUrl(), 'prev_page_url' => $q->previousPageUrl(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function show(Request $request, SupplierRequest $supplierRequest)
    {
        $uid = $request->user()->id;
        abort_unless(in_array($uid, [$supplierRequest->original_supplier_id, $supplierRequest->requesting_reseller_id], true), 404);
        $seo = Seo::make('Supplier request', index: false);
        $r = PresentCommerce::supplierRequest($supplierRequest, $uid);
        $open = in_array($supplierRequest->status, SupplierRequest::OPEN, true);

        return Inertia::render('Requests/Show', [
            'request' => $r,
            'actions' => $r['role'] === 'supplier'
                ? array_values(array_filter([$open ? 'confirm' : null, $open ? 'propose' : null, $open ? 'decline' : null, $supplierRequest->status === 'accepted' ? 'fulfil' : null, ($open || $supplierRequest->status === 'accepted') ? 'cancel' : null]))
                : array_values(array_filter([in_array($supplierRequest->status, ['availability_confirmed', 'terms_proposed'], true) ? 'accept' : null, ($open || $supplierRequest->status === 'accepted') ? 'cancel' : null])),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'requested_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'request_type' => ['required', Rule::in(SupplierRequest::TYPES)],
            'fulfilment_method' => ['required', Rule::in(SupplierRequest::FULFILMENT)],
            'message' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        if (! empty($data['idempotency_key']) && ($dup = SupplierRequest::query()->where('idempotency_key', $data['idempotency_key'])->where('requesting_reseller_id', $request->user()->id)->first())) {
            return redirect()->route('requests.show', $dup);
        }
        $req = $this->resale->sendRequest($request->user(), $product, $data);

        return redirect()->route('requests.show', $req)->with('success', 'Request sent to the supplier.');
    }

    public function act(Request $request, SupplierRequest $supplierRequest)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'decline', 'propose', 'fulfil', 'cancel', 'accept'])],
            'response' => ['nullable', 'string', 'max:1000'],
            'proposed_unit_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ]);
        $user = $request->user();
        if ($user->id === $supplierRequest->original_supplier_id && $data['action'] !== 'accept') {
            $this->resale->supplierRespond($supplierRequest, $user, $data['action'], [
                'response' => $data['response'] ?? null,
                'proposed_unit_price_minor' => isset($data['proposed_unit_price']) ? (int) round($data['proposed_unit_price'] * 100) : null,
            ]);
        } elseif ($user->id === $supplierRequest->requesting_reseller_id) {
            $this->resale->resellerRespond($supplierRequest, $user, $data['action']);
        } else {
            abort(403);
        }

        return back()->with('success', 'Request updated.');
    }
}
