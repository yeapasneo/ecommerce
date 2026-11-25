<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
