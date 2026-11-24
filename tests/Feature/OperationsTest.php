<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Messaging\OrderNotifier;
use App\Models\BlockedPhone;
use App\Models\Brand;
use App\Models\Category;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->withCredentials(); // send the session cookie with JSON requests too, like the browser
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'RAM', 'slug' => 'ram'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Test RAM', 'slug' => 'test-ram', 'sku' => 'RAM-1', 'price' => 5000, 'stock' => 10, 'stock_status' => 'in_stock',
        ]);
    }

    protected function smsOn(): void
    {
        Setting::put(['sms_provider' => 'bulksmsbd', 'sms_api_key' => 'KEY', 'sms_sender_id' => 'TechNest']);
    }

    protected function checkout(array $overrides = [])
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);

        return $this->post('/checkout', $overrides + [
            'first_name' => 'Rahim', 'phone' => '01712345678', 'email' => 'rahim@example.com', 'address' => 'Road 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'agree' => true,
        ]);
    }

    // ── Notifications ────────────────────────────────────────────────

    public function test_new_order_sends_sms_customer_email_and_shop_alert(): void
    {
        $this->smsOn();
        Setting::put(['notify_admin_email' => 'shop@example.com']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202, 'success_message' => 'SMS Submitted Successfully'])]);
        Mail::fake();

        $this->checkout()->assertRedirect();
        $order = Order::latest('id')->first();

        Http::assertSent(fn ($r) => $r['number'] === '8801712345678' && $r['senderid'] === 'TechNest' && $r['api_key'] === 'KEY'
            && str_contains($r['message'], "#{$order->order_number}") && str_contains($r['message'], 'Tk 5,060'));
        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo('rahim@example.com') && ! $m->forAdmin);
        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo('shop@example.com') && $m->forAdmin);
        $this->assertSame(1, NotificationLog::where(['order_id' => $order->id, 'channel' => 'sms', 'status' => 'sent'])->count());
    }

    public function test_status_changes_notify_with_the_tracking_link(): void
    {
        $this->smsOn();
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        $this->checkout();
        $order = Order::latest('id')->first();
        $order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1', 'courier_tracking_code' => 'ABC123']);

        app(OrderService::class)->changeStatus($order, 'shipped');

        Http::assertSent(fn ($r) => str_contains($r['message'], 'on the way with Steadfast') && str_contains($r['message'], 'https://steadfast.com.bd/t/ABC123'));
    }

    public function test_switched_off_events_and_missing_gateway_send_nothing(): void
    {
        Http::fake();
        $this->checkout(); // SMS not set up
        $this->smsOn();
        Setting::put(['notify_sms_order_cancelled' => '0']);
        app(OrderService::class)->changeStatus(Order::latest('id')->first(), 'cancelled');

        Http::assertNothingSent();
    }

    public function test_failed_sms_is_logged(): void
    {
        $this->smsOn();
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 1007, 'error_message' => 'Balance Insufficient'])]);
        $this->checkout();

        $log = NotificationLog::where('channel', 'sms')->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Balance Insufficient', $log->error);
    }

    public function test_template_placeholders(): void
    {
        $order = new Order(['first_name' => 'Rahim', 'order_number' => '123', 'total' => 25000, 'status' => 'shipped', 'phone' => '017']);
        $this->assertSame('Hi Rahim, #123 Tk 25,000 {unknown}', OrderNotifier::render('Hi {name}, #{order} Tk {total} {unknown}', $order));
    }

    // ── Checkout verification ────────────────────────────────────────

    public function test_checkout_requires_a_verified_phone_when_otp_is_on(): void
    {
        $this->smsOn();
        Setting::put(['checkout_otp' => '1', 'notify_sms_order_placed' => '0']);
        $sent = null;
        Http::fake(['bulksmsbd.net/*' => function ($r) use (&$sent) {
            $sent = $r['message'];

            return Http::response(['response_code' => 202]);
        }]);

        $this->checkout()->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::count());

        $this->postJson('/checkout/otp/send', ['phone' => '+880 1712-345678'])->assertOk();
        preg_match('/\b(\d{6})\b/', $sent, $m);
        $code = $m[1];

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson('/checkout/otp/verify', ['phone' => '01712345678', 'code' => $wrong])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/checkout/otp/verify', ['phone' => '01712345678', 'code' => $code])->assertOk();

        $this->checkout()->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_otp_locks_after_too_many_wrong_codes(): void
    {
        $this->smsOn();
        Setting::put(['checkout_otp' => '1']);
        Http::fake(['*' => Http::response(['response_code' => 202])]);
        $this->postJson('/checkout/otp/send', ['phone' => '01712345678'])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/checkout/otp/verify', ['phone' => '01712345678', 'code' => '000001']);
        }
        $this->postJson('/checkout/otp/verify', ['phone' => '01712345678', 'code' => '000001'])
            ->assertJsonValidationErrors(['code' => 'Too many wrong codes. Send a new one.']);
    }

    // ── Fraud check ──────────────────────────────────────────────────

    public function test_customer_history_matches_any_phone_format(): void
    {
        foreach (['delivered', 'delivered', 'delivered', 'cancelled'] as $i => $status) {
            Order::create(['order_number' => "X{$i}", 'first_name' => 'R', 'phone' => ['01712345678', '+8801712345678', '017 1234 5678', '8801712345678'][$i],
                'address' => 'A', 'city' => 'Dhaka', 'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
                'status' => $status, 'subtotal' => 1, 'delivery_charge' => 0, 'discount' => 0, 'total' => 1]);
        }

        $this->actingAs($this->admin)->getJson('/admin/fraud-check?phone=01712345678')->assertOk()
            ->assertJson(['internal' => ['total' => 4, 'delivered' => 3, 'cancelled' => 1, 'ratio' => 75], 'risk' => 'medium', 'blocked' => null]);
    }

    public function test_courier_history_from_bd_courier(): void
    {
        Setting::put(['fraud_provider' => 'bdcourier', 'bdcourier_api_key' => 'BDC']);
        Http::fake(['bdcourier.com/api/courier-check' => Http::response(['status' => 'success', 'courier_data' => [
            'pathao' => ['name' => 'Pathao', 'total_parcel' => 10, 'success_parcel' => 2, 'cancelled_parcel' => 8],
            'steadfast' => ['name' => 'Steadfast', 'total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0],
            'summary' => ['total_parcel' => 10, 'success_parcel' => 2, 'cancelled_parcel' => 8, 'success_ratio' => 20],
        ]])]);

        $this->actingAs($this->admin)->getJson('/admin/fraud-check?phone=01712345678&external=1')->assertOk()
            ->assertJson(['risk' => 'high', 'external' => ['total' => 10, 'delivered' => 2, 'ratio' => 20, 'couriers' => [['name' => 'Pathao', 'cancelled' => 8]]]])
            ->assertJsonCount(1, 'external.couriers');
        Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Bearer BDC' && $r['phone'] === '01712345678');
    }

    public function test_blocked_numbers_lose_cash_on_delivery(): void
    {
        $this->actingAs($this->admin)->post('/admin/blocked-phones', ['phone' => '+8801712345678', 'reason' => 'Refused 3 parcels'])->assertSessionHasNoErrors();
        $this->assertTrue(BlockedPhone::isBlocked('01712345678'));
        auth()->logout();

        $this->checkout()->assertSessionHasErrors(['payment_method' => 'Cash on delivery is not available for this number. Please pay online.']);

        Setting::put(['fraud_block_mode' => 'all']);
        $this->checkout()->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::count());

        $this->actingAs($this->admin)->post('/admin/blocked-phones/unblock', ['phone' => '01712345678']);
        $this->assertFalse(BlockedPhone::isBlocked('01712345678'));
    }

    public function test_orders_list_flags_blocked_customers(): void
    {
        $this->checkout();
        BlockedPhone::create(['phone' => '01712345678']);

        $this->actingAs($this->admin)->get('/admin/orders')->assertInertia(fn (Assert $p) => $p->where('orders.data.0.flagged', true));
        $this->actingAs($this->admin)->get('/admin/orders/'.Order::first()->id)->assertInertia(fn (Assert $p) => $p->where('validity.risk', 'blocked'));
    }

    // ── Staff orders ─────────────────────────────────────────────────

    protected function staffOrder(array $overrides = []): array
    {
        return array_merge([
            'source' => 'phone', 'status' => 'processing',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 4500]],
            'first_name' => 'Karim', 'phone' => '01812345678', 'address' => 'House 9, Mirpur', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'delivery_charge' => 80,
            'discount_type' => 'percent', 'discount_value' => 10,
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'notify' => false,
        ], $overrides);
    }

    public function test_staff_create_a_phone_order(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder())->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::with('items', 'histories')->latest('id')->first();

        // 2 × 4,500 = 9,000 − 10% + 80 delivery
        $this->assertSame([9000.0, 900.0, 80.0, 8180.0], [$order->subtotal, $order->discount, $order->delivery_charge, $order->total]);
        $this->assertSame(['phone', 'processing', 4500.0], [$order->source, $order->status, $order->items[0]->price]);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertStringContainsString('Order created by '.$this->admin->name, $order->histories[0]->comment);
    }

    public function test_walk_in_sale_needs_no_customer_details(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder([
            'source' => 'in_shop', 'status' => 'delivered', 'first_name' => '', 'phone' => '', 'address' => '', 'city' => '',
            'delivery_method' => 'store_pickup', 'delivery_charge' => 500, 'discount_value' => 0, 'payment_method' => 'cash', 'payment_status' => 'paid',
        ]))->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertSame(['Walk-in customer', 0.0, 9000.0, 'paid', 'delivered'], [$order->first_name, $order->delivery_charge, $order->total, $order->payment_status, $order->status]);
        $this->assertNotNull($order->paid_at);
    }

    public function test_staff_orders_are_validated(): void
    {
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder(['items' => []]))->assertSessionHasErrors('items');
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder(['items' => [['product_id' => $this->product->id, 'quantity' => 50, 'price' => 1]]]))
            ->assertSessionHasErrors(['items' => 'Only 10 unit(s) of Test RAM are in stock.']);
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_staff_order_can_send_the_confirmation_sms(): void
    {
        $this->smsOn();
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        $this->actingAs($this->admin)->post('/admin/orders', $this->staffOrder(['notify' => true]));

        Http::assertSent(fn ($r) => $r['number'] === '8801812345678');
    }

    public function test_product_search_and_customer_lookup(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/products/search?q=ram-1')->assertOk()
            ->assertJson([['id' => $this->product->id, 'name' => 'Test RAM', 'price' => 5000, 'purchasable' => true]]);

        $this->checkout();
        $this->actingAs($this->admin)->getJson('/admin/customers/lookup?phone=8801712345678')->assertOk()
            ->assertJson(['found' => true, 'last' => ['first_name' => 'Rahim', 'address' => 'Road 1'], 'validity' => ['internal' => ['total' => 1]]]);
    }

    // ── Go-live ──────────────────────────────────────────────────────

    public function test_system_status_flags_the_demo_password(): void
    {
        $this->actingAs($this->admin)->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/System')
            ->where('checks', fn ($checks) => collect($checks)->firstWhere('label', 'Admin passwords')['state'] === 'fail'));
    }

    public function test_scheduled_courier_sync_refreshes_open_parcels(): void
    {
        Setting::put(['steadfast_enabled' => '1', 'steadfast_api_key' => 'K', 'steadfast_secret_key' => 'S']);
        $this->checkout();
        $order = Order::latest('id')->first();
        $order->update(['status' => 'shipped', 'courier' => 'steadfast', 'courier_consignment_id' => '555', 'courier_status' => 'in_review', 'courier_sent_at' => now()]);
        Http::fake(['*/status_by_cid/555' => Http::response(['status' => 200, 'delivery_status' => 'delivered'])]);

        $this->artisan('couriers:sync')->expectsOutputToContain('1 changed')->assertSuccessful();
        $this->assertSame('delivered', $order->fresh()->status);
    }
}
