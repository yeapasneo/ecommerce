<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Stock bought from suppliers: draft → ordered → received (in one or more deliveries). */
class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->status, PurchaseOrder::STATUSES) ? $request->status : null;

        return Inertia::render('Admin/PurchaseOrders/Index', [
            'orders' => PurchaseOrder::with('supplier:id,name')->withCount('items')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'ilike', "%{$s}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('name', 'ilike', "%{$s}%"))))
                ->latest('id')->paginate($this->perPage())->withQueryString(),
            'filters' => ['status' => $status, 'search' => $request->search],
            'statuses' => PurchaseOrder::STATUSES,
            'counts' => PurchaseOrder::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        // "Reorder" from the low-stock list pre-fills the product.
        $prefill = [];
        if ($request->filled('product')) {
            $p = Product::find($request->integer('product'));
            $v = $request->filled('variant') ? ProductVariant::find($request->integer('variant')) : null;
            if ($p) {
                $prefill[] = ['product_id' => $p->id, 'variant_id' => $v?->id, 'name' => $p->name.($v ? " ({$v->name})" : ''),
                    'sku' => $v?->sku ?: $p->sku, 'quantity' => 1, 'unit_cost' => $v?->cost_price ?? $p->cost_price ?? 0, 'received_quantity' => 0];
            }
        }

        return Inertia::render('Admin/PurchaseOrders/Form', ['order' => null, 'prefill' => $prefill, ...$this->options()]);
    }

    public function store(Request $request)
    {
        $po = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $po = PurchaseOrder::create($data['attrs'] + ['number' => PurchaseOrder::nextNumber(), 'created_by' => $request->user()->id]);
            $this->syncItems($po, $data['items']);

            return $po;
        });

        return redirect()->route('admin.purchase-orders.show', $po)->with('success', "Purchase order {$po->number} saved.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'creator:id,name', 'items.product:id,name,slug,sku,image', 'items.variant:id,name,sku']);

        return Inertia::render('Admin/PurchaseOrders/Show', [
            'order' => $purchaseOrder,
            'statuses' => PurchaseOrder::STATUSES,
            'movements' => StockMovement::where('reference_type', $purchaseOrder->getMorphClass())->where('reference_id', $purchaseOrder->id)
                ->with('user:id,name')->latest('id')->get(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 403, 'Received or cancelled purchase orders cannot be edited.');
        $purchaseOrder->load('items.product:id,sku', 'items.variant:id,sku');

        return Inertia::render('Admin/PurchaseOrders/Form', ['order' => $purchaseOrder, 'prefill' => [], ...$this->options()]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 403);
        DB::transaction(function () use ($request, $purchaseOrder) {
            $data = $this->validated($request);
            $purchaseOrder->update($data['attrs']);
            $this->syncItems($purchaseOrder, $data['items']);
        });

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order saved.');
    }

    /** Mark as sent to the supplier, or cancel. */
    public function status(Request $request, PurchaseOrder $purchaseOrder)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['ordered', 'cancelled'])]])['status'];
        if ($status === 'ordered' && $purchaseOrder->status !== 'draft') {
            abort(422);
        }
        if ($status === 'cancelled' && ! in_array($purchaseOrder->status, ['draft', 'ordered'], true)) {
            throw ValidationException::withMessages(['status' => 'Stock has already been received for this order.']);
        }
        $purchaseOrder->update(['status' => $status]);

        return back()->with('success', $status === 'ordered' ? 'Marked as ordered.' : 'Purchase order cancelled.');
    }

    /** Book a delivery into stock. Each line can be received in full or in part. */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(in_array($purchaseOrder->status, ['draft', 'ordered', 'partial'], true), 422);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'update_cost' => ['boolean'],
        ]);

        $received = DB::transaction(function () use ($purchaseOrder, $data, $request) {
            $po = PurchaseOrder::lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);
            $count = 0;
            Stock::as('purchase', $po, function () use ($po, $data, $request, &$count) {
                foreach ($po->items as $item) {
                    $qty = (int) ($data['items'][$item->id] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $left = $item->quantity - $item->received_quantity;
                    if ($qty > $left) {
                        throw ValidationException::withMessages(["items.{$item->id}" => "Only {$left} unit(s) of {$item->name} are still expected."]);
                    }
                    $product = Product::lockForUpdate()->find($item->product_id);
                    $variant = $item->variant_id ? ProductVariant::lockForUpdate()->find($item->variant_id) : null;
                    if (! $product) {
                        continue;
                    }
                    $cost = $request->boolean('update_cost', true) ? ['cost_price' => $item->unit_cost] : [];
                    if ($variant) {
                        $variant->update(['stock' => $variant->stock + $qty] + $cost);
                    }
                    $product->update(['stock' => $product->stock + $qty]
                        + (! $variant ? $cost : [])
                        + (in_array($product->stock_status, ['out_of_stock', 'upcoming'], true) ? ['stock_status' => 'in_stock'] : []));
                    $item->increment('received_quantity', $qty);
                    $count += $qty;
                }
            });
            $po->load('items');
            $done = $po->items->every(fn ($i) => $i->received_quantity >= $i->quantity);
            $po->update(['status' => $done ? 'received' : 'partial', 'received_at' => $done ? now() : $po->received_at]);

            return $count;
        });

        return back()->with('success', $received ? "{$received} unit(s) added to stock." : 'Nothing was received.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft' || $purchaseOrder->status === 'cancelled', 403);
        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    protected function options(): array
    {
        return ['suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name'])];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'expected_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], ['items.required' => 'Add at least one product.']);

        return ['attrs' => collect($data)->only('supplier_id', 'expected_at', 'note')->all(), 'items' => $data['items']];
    }

    protected function syncItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();
        $total = 0;
        foreach ($items as $row) {
            $product = Product::find($row['product_id']);
            $variant = ! empty($row['variant_id']) ? $product->variants()->find($row['variant_id']) : null;
            $po->items()->create([
                'product_id' => $product->id, 'variant_id' => $variant?->id,
                'name' => $product->name.($variant ? " ({$variant->name})" : ''),
                'quantity' => (int) $row['quantity'], 'unit_cost' => (float) $row['unit_cost'],
            ]);
            $total += $row['quantity'] * $row['unit_cost'];
        }
        $po->update(['total' => $total]);
    }
}
