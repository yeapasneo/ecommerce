<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Product $a;

    protected Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $cat = Category::create(['name' => 'RAM', 'slug' => 'ram']);
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->a = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Item A', 'slug' => 'a', 'sku' => 'A', 'price' => 1000, 'stock' => 10, 'stock_status' => 'in_stock']);
        $this->b = Product::create(['category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Item B', 'slug' => 'b', 'sku' => 'B', 'price' => 500, 'stock' => 5, 'stock_status' => 'in_stock']);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'source' => 'phone', 'status' => 'processing',
            'items' => [['product_id' => $this->a->id, 'quantity' => 2, 'price' => 1000]],
            'first_name' => 'Karim', 'phone' => '01812345678', 'address' => 'House 9, Mirpur', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'delivery_charge' => 60,
            'discount_type' => 'amount', 'discount_value' => 0, 'tax_type' => 'percent', 'tax_value' => 0,
            'payment_method' => 'cod', 'payment_status' => 'unpaid',
        ], $overrides);
    }

    public function test_vat_and_partial_payment(): void
    {
        // 2,000 − 200 discount = 1,800; 5% VAT = 90; + 60 delivery = 1,950; 500 paid → 1,450 due
        $this->actingAs($this->admin)->post('/admin/orders', $this->payload([
            'discount_value' => 200, 'tax_value' => 5, 'payment_status' => 'partial', 'paid_amount' => 500,
        ]))->assertSessionHasNoErrors();

        $o = Order::first();
        $this->assertSame([200.0, 90.0, 1950.0, 500.0, 1450.0, 'partial'], [$o->discount, $o->tax, $o->total, $o->paid_amount, $o->due_amount, $o->payment_status]);

        // Paying the rest from the order page
        $this->actingAs($this->admin)->put("/admin/orders/{$o->id}", ['status' => 'processing', 'payment_status' => 'partial', 'paid_amount' => 1950]);
        $this->assertSame(['paid', 0.0], [$o->fresh()->payment_status, $o->fresh()->due_amount]);
    }

    public function test_editing_an_order_moves_stock_and_logs_the_change(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->payload());
        $o = Order::first();
        $this->assertSame(8, $this->a->fresh()->stock);

        $this->actingAs($this->admin)->put("/admin/orders/{$o->id}/details", $this->payload([
            'items' => [['product_id' => $this->a->id, 'quantity' => 1, 'price' => 1000], ['product_id' => $this->b->id, 'quantity' => 3, 'price' => 450]],
            'address' => 'New address, Uttara',
        ]))->assertSessionHasNoErrors();

        $o->refresh();
        $this->assertSame([9, 2], [$this->a->fresh()->stock, $this->b->fresh()->stock]);
        $this->assertSame([2350.0, 2410.0, 'New address, Uttara', 2], [$o->subtotal, $o->total, $o->address, $o->items()->count()]);
        $this->assertStringContainsString('Total ৳2,060 → ৳2,410', $o->histories()->latest('id')->first()->comment);
    }

    public function test_failed_edit_keeps_the_original_order(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->payload());
        $o = Order::first();

        $this->actingAs($this->admin)->put("/admin/orders/{$o->id}/details", $this->payload(['items' => [['product_id' => $this->b->id, 'quantity' => 99, 'price' => 1]]]))
            ->assertSessionHasErrors('items');
        $this->assertSame([8, 5, 1], [$this->a->fresh()->stock, $this->b->fresh()->stock, $o->items()->count()]);
    }

    public function test_shipped_orders_cannot_be_edited(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->payload());
        $o = Order::first();
        $o->update(['status' => 'shipped']);

        $this->actingAs($this->admin)->get("/admin/orders/{$o->id}/edit")->assertRedirect("/admin/orders/{$o->id}");
        $this->actingAs($this->admin)->put("/admin/orders/{$o->id}/details", $this->payload())->assertForbidden();
    }

    public function test_saved_smtp_settings_apply_to_password_reset_emails(): void
    {
        Setting::put(['mail_host' => 'smtp.example.com', 'mail_from_address' => 'orders@shop.test']);
        $this->app->forgetInstance('mail.manager');
        app('mail.manager');

        $this->assertSame(['smtp', 'smtp.example.com', 'orders@shop.test'], [config('mail.default'), config('mail.mailers.smtp.host'), config('mail.from.address')]);
    }
}
