<?php

namespace Tests\Feature;

use App\Models\AbandonedCheckout;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AbandonedCarts;
use App\Support\Emi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommerceFeaturesTest extends TestCase
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
            'category_id' => Category::create(['name' => 'Mouse', 'slug' => 'mouse'])->id,
            'brand_id' => Brand::create(['name' => 'Logitech', 'slug' => 'logitech'])->id,
            'name' => 'Logitech G102 Gaming Mouse', 'slug' => 'logitech-g102', 'sku' => 'G102', 'price' => 2000, 'stock' => 10, 'stock_status' => 'in_stock',
        ]);
    }

    protected function staff(string $role): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => $role]);
    }

    protected function order(array $attrs = []): Order
    {
        static $n = 0;
        $n++;
        $order = Order::create($attrs + [
            'order_number' => "R{$n}", 'first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'A', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing',
            'subtotal' => 4000, 'delivery_charge' => 60, 'discount' => 0, 'total' => 4060,
        ]);
        $order->items()->create(['product_id' => $this->product->id, 'name' => $this->product->name, 'price' => 2000, 'quantity' => 2, 'total' => 4000]);

        return $order;
    }

    // ── Staff roles & activity log ───────────────────────────────────

    public function test_roles_limit_what_staff_can_open(): void
    {
        $orders = $this->staff('orders');
        $this->actingAs($orders)->get('/admin')->assertOk();
        $this->actingAs($orders)->get('/admin/orders')->assertOk();
        $this->actingAs($orders)->get('/admin/products')->assertForbidden();
        $this->actingAs($orders)->get('/admin/settings')->assertForbidden();
        $this->actingAs($orders)->get('/admin/staff')->assertForbidden();
        $this->actingAs($orders)->get('/admin/reports')->assertForbidden();
        $this->actingAs($orders)->get('/admin')->assertInertia(fn (Assert $p) => $p->where('auth.user.permissions', ['orders', 'engagement']));

        $accountant = $this->staff('accountant');
        $this->actingAs($accountant)->get('/admin/reports')->assertOk();
        $this->actingAs($accountant)->get('/admin/orders')->assertForbidden();

        $this->actingAs($this->staff('catalog'))->get('/admin/products')->assertOk();
        $this->actingAs($this->owner)->get('/admin/staff')->assertOk();
    }

    public function test_owner_manages_staff_but_cannot_lock_the_shop_out(): void
    {
        $customer = User::factory()->create(['email' => 'karim@example.com']);
        $this->actingAs($this->owner)->post('/admin/staff', ['email' => 'karim@example.com', 'role' => 'orders'])->assertSessionHasNoErrors();
        $this->assertSame([true, 'orders'], [$customer->fresh()->is_admin, $customer->fresh()->role]);

        $this->actingAs($this->owner)->post('/admin/staff', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'secret123', 'role' => 'catalog'])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'new@example.com')->value('is_admin'));

        // The only owner can't demote or block themselves.
        $this->actingAs($this->owner)->put("/admin/staff/{$this->owner->id}", ['role' => 'manager', 'is_active' => true])->assertSessionHasErrors('role');
        $this->assertSame('owner', $this->owner->fresh()->role);

        $this->actingAs($this->owner)->delete("/admin/staff/{$customer->id}")->assertSessionHasNoErrors();
        $this->assertFalse($customer->fresh()->is_admin);

        // An order manager can't grant admin access from the customer page.
        $orders = $this->staff('orders');
        $shopper = User::factory()->create();
        $this->actingAs($orders)->put("/admin/customers/{$shopper->id}", ['name' => $shopper->name, 'email' => $shopper->email, 'is_admin' => true, 'is_active' => true]);
        $this->assertFalse((bool) $shopper->fresh()->is_admin);
    }

    public function test_changes_are_logged_with_who_made_them(): void
    {
        $this->actingAs($this->owner)->put("/admin/products/{$this->product->id}", [
            'name' => 'Logitech G102 Mouse', 'sku' => 'G102', 'category_id' => $this->product->category_id, 'brand_id' => $this->product->brand_id,
            'price' => 1900, 'stock' => 10, 'stock_status' => 'in_stock', 'is_active' => true, 'is_featured' => false,
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::where('subject_type', 'Product')->where('action', 'updated')->latest('id')->first();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertEquals([2000, 1900], $log->changes['price']);
        $this->assertStringContainsString('Logitech G102 Mouse', $log->description);

        $this->actingAs($this->owner)->put('/admin/settings', ['site_name' => 'My Shop', 'delivery_charge_inside' => 60, 'delivery_charge_outside' => 120, 'express_delivery_charge' => 150, 'free_delivery_over' => 0])->assertSessionHasNoErrors();
        $this->assertTrue(ActivityLog::where('description', 'like', 'Changed settings:%site name%')->exists());

        $this->actingAs($this->owner)->get('/admin/activity')->assertInertia(fn (Assert $p) => $p->component('Admin/Activity')->where('logs.total', ActivityLog::count()));
        // Shopper actions aren't staff activity.
        $before = ActivityLog::count();
        auth()->logout();
        $this->order();
        $this->assertSame($before, ActivityLog::count());
    }

    // ── Reports ──────────────────────────────────────────────────────

    public function test_sales_report_and_csv_export(): void
    {
        $this->order(['status' => 'delivered', 'payment_status' => 'paid', 'paid_amount' => 4060]);
        $this->order(['status' => 'processing', 'payment_status' => 'partial', 'paid_amount' => 1000]);
        $this->order(['status' => 'cancelled']);

        $this->actingAs($this->owner)->get('/admin/reports')->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Reports')
            ->where('totals.orders', 2)
            ->where('totals.revenue', 8120)
            ->where('totals.collected', 5060)
            ->where('totals.outstanding', 3060)
            ->where('cancelled.orders', 1)
            ->where('topProducts.0.qty', 4));

        $csv = $this->actingAs($this->owner)->get('/admin/reports/export/orders')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFOrder,Date", $csv);
        $this->assertCount(4, array_filter(explode("\n", $csv))); // header + 3 orders

        $products = $this->actingAs($this->owner)->get('/admin/reports/export/products')->assertOk()->streamedContent();
        $this->assertStringContainsString('Logitech G102 Gaming Mouse', $products);
    }

    // ── Search ───────────────────────────────────────────────────────

    public function test_search_forgives_typos(): void
    {
        foreach (['logitec mouse', 'logitech g102', 'gamin mouse', 'G102'] as $q) {
            $this->get('/search?q='.urlencode($q))->assertInertia(fn (Assert $p) => $p->has('products.data', 1));
        }
        $this->get('/search?q=keyboard')->assertInertia(fn (Assert $p) => $p->has('products.data', 0));
        $this->getJson('/search/suggest?q=logitek')->assertOk()->assertJsonPath('0.slug', 'logitech-g102');
    }

    // ── Flash sales ──────────────────────────────────────────────────

    public function test_flash_sale_price_applies_while_running(): void
    {
        $this->actingAs($this->owner)->post('/admin/flash-sales', [
            'title' => 'Eid Sale', 'starts_at' => now()->subHour()->format('Y-m-d H:i'), 'ends_at' => now()->addDay()->format('Y-m-d H:i'), 'is_active' => true,
            'items' => [['product_id' => $this->product->id, 'sale_price' => 1500]],
        ])->assertSessionHasNoErrors();
        // Staff screens keep the normal price.
        $this->actingAs($this->owner)->get("/admin/products/{$this->product->id}/edit")->assertInertia(fn (Assert $p) => $p->where('product.price', 2000));
        auth()->logout();
        Product::$flashPrices = true;

        $this->get('/product/logitech-g102')->assertInertia(fn (Assert $p) => $p->where('product.price', 1500)->where('product.regular_price', 2000)->where('flash.title', 'Eid Sale'));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('flashSale.title', 'Eid Sale')->where('flashSale.products.0.price', 1500));

        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/cart')->assertInertia(fn (Assert $p) => $p->where('cart.subtotal', 1500));

        FlashSale::first()->update(['ends_at' => now()->subMinute()]);
        $this->get('/product/logitech-g102')->assertInertia(fn (Assert $p) => $p->where('product.price', 2000)->where('flash', null));
    }

    // ── Abandoned carts ──────────────────────────────────────────────

    public function test_abandoned_cart_is_tracked_reminded_and_restored(): void
    {
        Setting::put(['abandoned_cart_enabled' => '1', 'sms_provider' => 'bulksmsbd', 'sms_api_key' => 'KEY', 'sms_sender_id' => 'Shop']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        Mail::fake();

        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 2]);
        $this->postJson('/checkout/contact', ['name' => 'Rahim Uddin', 'phone' => '01712345678', 'email' => 'rahim@example.com'])->assertNoContent();
        $cart = AbandonedCheckout::firstOrFail();
        $this->assertEquals(4000, $cart->total);

        $this->artisan('carts:remind')->assertSuccessful();
        $this->assertNull($cart->fresh()->reminded_at); // not due yet

        $this->travel(3)->hours();
        $this->artisan('carts:remind')->assertSuccessful();
        $this->assertNotNull($cart->fresh()->reminded_at);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Rahim') && str_contains($r['message'], "/cart/recover/{$cart->token}"));
        $this->assertSame(2, NotificationLog::where('event', 'abandoned_cart')->where('status', 'sent')->count());

        // Opening the link in a new browser brings the items back.
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->flushSession();
        $this->get("/cart/recover/{$cart->token}")->assertRedirect('/checkout');
        $this->get('/cart')->assertInertia(fn (Assert $p) => $p->has('cart.items', 1)->where('cart.items.0.quantity', 2));

        // Ordering marks it recovered.
        app(AbandonedCarts::class)->markRecovered($this->order(['phone' => '+8801712345678']));
        $this->assertNotNull($cart->fresh()->recovered_at);
    }

    public function test_cart_is_not_tracked_when_turned_off(): void
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->postJson('/checkout/contact', ['phone' => '01712345678'])->assertNoContent();
        $this->assertSame(0, AbandonedCheckout::count());
    }

    // ── EMI ──────────────────────────────────────────────────────────

    public function test_emi_plans(): void
    {
        $this->assertNull(Emi::plans(20000)); // off by default
        Setting::put(['emi_enabled' => '1', 'emi_min_amount' => '5000', 'emi_plans' => '3:0, 12:10']);
        $this->assertNull(Emi::plans(4000));
        $plans = Emi::plans(12000)['plans'];
        $this->assertEquals(['months' => 3, 'rate' => 0, 'total' => 12000, 'monthly' => 4000], $plans[0]);
        $this->assertEquals(13200, $plans[1]['total']);
        $this->assertEquals(1100, $plans[1]['monthly']);
    }

    // ── Bangla ───────────────────────────────────────────────────────

    public function test_storefront_switches_to_bangla(): void
    {
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('locale', 'en'));
        $this->get('/locale/bn')->assertRedirect()->assertCookie('locale', 'bn');
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('locale', 'bn')->where('translations.Add to Cart', fn ($v) => $v !== 'Add to Cart' && $v !== null));
        $this->get('/locale/fr')->assertNotFound();

        // The admin panel stays in English.
        $this->actingAs($this->owner)->get('/admin')->assertInertia(fn (Assert $p) => $p->where('translations', []));
    }
}
