<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->withCredentials();
        $this->owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'RAM', 'slug' => 'ram'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Acme 16GB DDR5', 'slug' => 'acme-16gb', 'sku' => 'RAM-16', 'price' => 5000, 'cost_price' => 4000, 'stock' => 10, 'stock_status' => 'in_stock',
        ]);
    }

    protected function sell(int $qty = 1, array $attrs = []): Order
    {
        return app(OrderService::class)->place([['product_id' => $this->product->id, 'quantity' => $qty]], fn ($subtotal) => $attrs + [
            'order_number' => 'T'.Str::random(6), 'first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'A', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing',
            'subtotal' => $subtotal, 'delivery_charge' => 60, 'discount' => 0, 'total' => $subtotal + 60,
        ]);
    }

    // ── Cost and profit ──────────────────────────────────────────────

    public function test_cost_is_recorded_at_sale_and_profit_is_reported(): void
    {
        $this->sell(2);
        $this->product->update(['cost_price' => 4500]); // later cost changes don't rewrite past profit
        $this->assertEquals(4000, OrderItem::first()->cost_price);

        $this->actingAs($this->owner)->get('/admin/reports')->assertInertia(fn (Assert $p) => $p
            ->where('totals.profit', 2000) // 2 × (5000 − 4000); delivery is not profit
            ->where('totals.margin', 20)
            ->where('totals.costCoverage', 100)
            ->where('topProducts.0.profit', 2000)
            ->where('profitable.0.name', 'Acme 16GB DDR5')
            ->where('categories.0.margin', 20));

        $csv = $this->actingAs($this->owner)->get('/admin/reports/export/products')->streamedContent();
        $this->assertStringContainsString('Cost,Profit,"Margin %"', $csv);
        $this->assertStringContainsString(',8000,2000,20,', $csv);
    }

    public function test_cost_price_is_never_shown_to_shoppers(): void
    {
        $this->get('/product/acme-16gb')->assertInertia(fn (Assert $p) => $p->missing('product.cost_price'));
        $this->assertStringNotContainsString('4000', json_encode(Product::first()->toCard()));
        $this->actingAs($this->owner)->get("/admin/products/{$this->product->id}/edit")->assertInertia(fn (Assert $p) => $p->where('product.cost_price', 4000));
    }

    // ── Stock history ────────────────────────────────────────────────

    public function test_sales_and_cancellations_are_recorded_in_stock_history(): void
    {
        $order = $this->sell(3);
        app(OrderService::class)->changeStatus($order, 'cancelled');

        $moves = StockMovement::orderBy('id')->get();
        $this->assertSame([['sale', -3, 7], ['cancel', 3, 10]], $moves->map(fn ($m) => [$m->reason, $m->change, $m->stock_after])->all());
        $this->assertTrue($moves[0]->reference->is($order));

        $this->actingAs($this->owner)->get('/admin/inventory?tab=history')->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Inventory')->has('movements.data', 2)->where('movements.data.1.reference_label.label', "Order #{$order->order_number}"));
    }

    public function test_variant_stock_is_tracked_per_variant_only(): void
    {
        $this->product->update(['has_variants' => true]);
        $variant = $this->product->variants()->create(['name' => 'Black', 'options' => ['Colour' => 'Black'], 'price' => 5000, 'stock' => 4, 'is_active' => true]);
        StockMovement::query()->delete();
        app(OrderService::class)->place([['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 1]], fn ($subtotal) => [
            'first_name' => 'R', 'phone' => '01712345678', 'address' => 'A', 'city' => 'Dhaka', 'delivery_method' => 'home_delivery',
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing', 'subtotal' => $subtotal, 'delivery_charge' => 0, 'discount' => 0, 'total' => $subtotal,
        ]);
        $this->assertSame([[$variant->id, -1, 3]], StockMovement::get()->map(fn ($m) => [$m->variant_id, $m->change, $m->stock_after])->all());
    }

    public function test_stock_count_correction(): void
    {
        $this->actingAs($this->owner)->post('/admin/inventory/adjust', ['product_id' => $this->product->id, 'stock' => 0, 'note' => 'Damaged box'])->assertSessionHas('success');
        $p = $this->product->fresh();
        $this->assertSame([0, 'out_of_stock'], [$p->stock, $p->stock_status]);
        $m = StockMovement::latest('id')->first();
        $this->assertSame(['adjustment', -10, 'Damaged box', $this->owner->id], [$m->reason, $m->change, $m->note, $m->user_id]);
    }

    // ── Suppliers and purchase orders ────────────────────────────────

    public function test_purchase_order_is_received_into_stock(): void
    {
        $this->product->update(['stock' => 0, 'stock_status' => 'out_of_stock']);
        $this->actingAs($this->owner)->post('/admin/suppliers', ['name' => 'Smart Tech BD', 'phone' => '01811111111', 'is_active' => true])->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();

        $this->actingAs($this->owner)->post('/admin/purchase-orders', [
            'supplier_id' => $supplier->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 3800]],
        ])->assertRedirect();
        $po = PurchaseOrder::with('items')->firstOrFail();
        $this->assertSame(['PO-00001', 'draft', 38000.0], [$po->number, $po->status, $po->total]);
        $item = $po->items->first();

        // Part delivery.
        $this->actingAs($this->owner)->post("/admin/purchase-orders/{$po->id}/receive", ['items' => [$item->id => 6], 'update_cost' => true])->assertSessionHas('success');
        $p = $this->product->fresh();
        $this->assertSame([6, 'in_stock', 3800.0], [$p->stock, $p->stock_status, $p->cost_price]);
        $this->assertSame('partial', $po->fresh()->status);

        // Can't receive more than ordered.
        $this->actingAs($this->owner)->post("/admin/purchase-orders/{$po->id}/receive", ['items' => [$item->id => 5]])->assertSessionHasErrors("items.{$item->id}");
        $this->actingAs($this->owner)->post("/admin/purchase-orders/{$po->id}/receive", ['items' => [$item->id => 4]]);
        $this->assertSame(['received', 10], [$po->fresh()->status, $this->product->fresh()->stock]);
        $this->assertSame(2, StockMovement::where('reason', 'purchase')->where('reference_id', $po->id)->count());

        // Received orders are locked.
        $this->actingAs($this->owner)->get("/admin/purchase-orders/{$po->id}/edit")->assertForbidden();
        $this->actingAs($this->owner)->get("/admin/purchase-orders/{$po->id}")->assertInertia(fn (Assert $p) => $p->component('Admin/PurchaseOrders/Show')->has('movements', 2));
    }

    public function test_low_stock_list_and_morning_email(): void
    {
        Setting::put(['low_stock_threshold' => '3', 'low_stock_alerts' => '1', 'notify_admin_email' => 'shop@example.com']);
        $this->product->update(['stock' => 2]);
        $this->actingAs($this->owner)->get('/admin/inventory')->assertInertia(fn (Assert $p) => $p->has('low', 1)->where('low.0.stock', 2));

        Mail::fake();
        $this->artisan('stock:low-alert')->assertSuccessful()->expectsOutputToContain('shop@example.com');
    }

    public function test_inventory_needs_catalog_permission(): void
    {
        $orders = User::factory()->create(['is_admin' => true, 'role' => 'orders']);
        foreach (['/admin/inventory', '/admin/purchase-orders', '/admin/suppliers'] as $url) {
            $this->actingAs($orders)->get($url)->assertForbidden();
        }
    }
}
