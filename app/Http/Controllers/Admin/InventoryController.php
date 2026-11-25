<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Services\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Low stock and the history of every stock change. */
class InventoryController extends Controller
{
    public static function threshold(): int
    {
        return max(0, (int) Setting::get('low_stock_threshold', 3));
    }

    /** Products and variants at or below the low-stock level (on sale, not pre-order or upcoming). */
    public static function lowStock(): Collection
    {
        $limit = self::threshold();
        $products = Product::where('is_active', true)->where('has_variants', false)->whereIn('stock_status', ['in_stock', 'out_of_stock'])
            ->where('stock', '<=', $limit)->orderBy('stock')->orderBy('name')->limit(500)
            ->get(['id', 'name', 'sku', 'stock', 'stock_status', 'cost_price', 'image'])
            ->map(fn ($p) => ['product_id' => $p->id, 'variant_id' => null, 'name' => $p->name, 'sku' => $p->sku, 'stock' => $p->stock, 'image_url' => $p->image_url]);
        $variants = ProductVariant::with('product:id,name,sku,image,is_active,stock_status')->where('is_active', true)->where('stock', '<=', $limit)
            ->whereHas('product', fn ($q) => $q->where('is_active', true)->whereNotIn('stock_status', ['pre_order', 'up_coming']))
            ->orderBy('stock')->limit(500)->get()
            ->map(fn ($v) => ['product_id' => $v->product_id, 'variant_id' => $v->id, 'name' => "{$v->product->name} ({$v->name})", 'sku' => $v->sku ?: $v->product->sku, 'stock' => $v->stock, 'image_url' => $v->image_url ?: $v->product->image_url]);

        return $products->concat($variants)->sortBy('stock')->values();
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['history', 'waiting'], true) ? $request->tab : 'low';
        $sold = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(30))->where('orders.status', '!=', 'cancelled')
            ->selectRaw("order_items.product_id || '-' || coalesce(order_items.variant_id, 0) as k, sum(order_items.quantity) as qty")->groupBy('k')->pluck('qty', 'k');

        return Inertia::render('Admin/Inventory', [
            'tab' => $tab,
            'threshold' => self::threshold(),
            'low' => $tab === 'low' ? self::lowStock()->map(fn ($r) => $r + ['sold30' => (int) ($sold[$r['product_id'].'-'.($r['variant_id'] ?? 0)] ?? 0)]) : [],
            'movements' => $tab === 'history' ? StockMovement::with(['product:id,name,slug', 'variant:id,name', 'user:id,name'])
                ->when($request->reason, fn ($q, $r) => $q->where('reason', $r))
                ->when($request->search, fn ($q, $s) => $q->whereHas('product', fn ($q) => $q->where('name', 'ilike', "%{$s}%")->orWhere('sku', 'ilike', "%{$s}%")))
                ->when($request->product, fn ($q, $id) => $q->where('product_id', $id))
                ->latest('id')->paginate($this->perPage())->withQueryString()
                ->through(fn ($m) => $m->toArray() + ['reference_label' => $this->referenceLabel($m)]) : null,
            'waiting' => $tab === 'waiting' ? StockAlert::where('type', 'back_in_stock')->whereNull('notified_at')
                ->selectRaw('product_id, variant_id, count(*) as people, max(created_at) as latest')->groupBy('product_id', 'variant_id')->orderByDesc('people')->limit(300)->get()
                ->load('product:id,name,sku,stock,image', 'variant:id,name,stock') : [],
            'reasons' => StockMovement::REASONS,
            'filters' => $request->only('reason', 'search', 'product'),
        ]);
    }

    /** Quick stock correction (counted stock), recorded as a manual change with a note. */
    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            Stock::as('adjustment', null, function () use ($product, $data) {
                if (! empty($data['variant_id'])) {
                    $variant = $product->variants()->lockForUpdate()->findOrFail($data['variant_id']);
                    $diff = $data['stock'] - $variant->stock;
                    $variant->update(['stock' => $data['stock']]);
                    $product->update(['stock' => max(0, $product->stock + $diff)]);
                } else {
                    $product->update(['stock' => $data['stock']] + ($data['stock'] > 0 && $product->stock_status === 'out_of_stock' ? ['stock_status' => 'in_stock'] : [])
                        + ($data['stock'] === 0 && $product->stock_status === 'in_stock' ? ['stock_status' => 'out_of_stock'] : []));
                }
            }, $data['note'] ?? 'Stock count');
        });

        return back()->with('success', 'Stock updated.');
    }

    protected function referenceLabel(StockMovement $m): ?array
    {
        $ref = $m->reference_type ? $m->reference : null;

        return match (true) {
            $ref instanceof Order => ['label' => "Order #{$ref->order_number}", 'url' => "/admin/orders/{$ref->id}"],
            $ref instanceof PurchaseOrder => ['label' => $ref->number, 'url' => "/admin/purchase-orders/{$ref->id}"],
            $ref instanceof ReturnRequest => ['label' => "Return {$ref->number}", 'url' => "/admin/returns/{$ref->id}"],
            default => null,
        };
    }
}
