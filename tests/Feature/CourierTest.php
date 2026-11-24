<?php

namespace Tests\Feature;

use App\Couriers\Pathao;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourierTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        Setting::put([
            'steadfast_enabled' => '1', 'steadfast_api_key' => 'SF-KEY', 'steadfast_secret_key' => 'SF-SECRET', 'steadfast_webhook_token' => str_repeat('t', 40),
            'pathao_enabled' => '1', 'pathao_client_id' => 'CID', 'pathao_client_secret' => 'CSECRET', 'pathao_username' => 'shop@example.com', 'pathao_password' => 'pw',
            'pathao_store_id' => '777', 'pathao_webhook_secret' => str_repeat('p', 40),
        ]);
        $this->order = Order::create([
            'order_number' => '26112400001', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '+8801712345678',
            'address' => 'House 5, Road 2, Banani', 'city' => 'Dhaka', 'delivery_method' => 'home_delivery',
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing',
            'subtotal' => 5000, 'delivery_charge' => 60, 'discount' => 0, 'total' => 5060,
        ]);
        $this->order->items()->create(['name' => 'Test RAM', 'sku' => 'RAM-1', 'price' => 5000, 'quantity' => 1, 'total' => 5000]);
    }

    protected function booking(array $overrides = []): array
    {
        return $overrides + [
            'recipient_name' => 'Rahim Uddin', 'recipient_phone' => '+8801712345678',
            'recipient_address' => 'House 5, Road 2, Banani, Dhaka', 'cod_amount' => 5060,
        ];
    }

    public function test_steadfast_booking_ships_the_order(): void
    {
        Http::fake(['portal.packzy.com/api/v1/create_order' => Http::response([
            'status' => 200, 'message' => 'Consignment has been created successfully.',
            'consignment' => ['consignment_id' => 1424107, 'invoice' => '26112400001', 'tracking_code' => '15BAEB8A', 'cod_amount' => 5060, 'status' => 'in_review'],
        ])]);

        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'steadfast']))
            ->assertSessionHas('success');

        Http::assertSent(fn ($r) => $r->header('Api-Key')[0] === 'SF-KEY' && $r->header('Secret-Key')[0] === 'SF-SECRET'
            && $r['invoice'] === '26112400001' && $r['recipient_phone'] === '01712345678' && $r['cod_amount'] == 5060);

        $order = $this->order->fresh();
        $this->assertSame(['steadfast', '1424107', '15BAEB8A', 'in_review', 'shipped'], [$order->courier, $order->courier_consignment_id, $order->courier_tracking_code, $order->courier_status, $order->status]);
        $this->assertSame('https://steadfast.com.bd/t/15BAEB8A', $order->courier_tracking_url);
    }

    public function test_steadfast_errors_are_shown_and_nothing_is_saved(): void
    {
        Http::fake(['*/create_order' => Http::response(['status' => 400, 'errors' => ['recipient_phone' => ['The recipient phone must be 11 characters.']]], 422)]);

        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'steadfast']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'must be 11 characters'));
        $this->assertNull($this->order->fresh()->courier);
    }

    public function test_steadfast_webhook_marks_the_order_delivered(): void
    {
        $this->order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1424107', 'courier_status' => 'in_review', 'status' => 'shipped']);
        $payload = ['notification_type' => 'delivery_status', 'consignment_id' => 1424107, 'invoice' => '26112400001', 'status' => 'delivered', 'delivery_charge' => 80];

        $this->postJson('/webhooks/courier/steadfast', $payload, ['Authorization' => 'Bearer wrong-token'])->assertStatus(401);
        $this->assertSame('shipped', $this->order->fresh()->status);

        $response = $this->postJson('/webhooks/courier/steadfast', $payload, ['Authorization' => 'Bearer '.str_repeat('t', 40)]);
        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertEmpty(array_filter($response->headers->getCookies(), fn ($c) => $c->getName() === config('session.cookie')), 'webhook must not start a session');

        $order = $this->order->fresh();
        $this->assertSame(['delivered', 'paid', 'delivered', 80.0], [$order->status, $order->payment_status, $order->courier_status, $order->courier_fee]);
        $this->assertTrue($order->histories()->where('comment', 'Steadfast: Delivered.')->exists());
    }

    public function test_courier_updates_can_be_kept_out_of_the_order_status(): void
    {
        Setting::put(['courier_sync_status' => '0']);
        $this->order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1424107', 'status' => 'shipped']);

        $this->postJson('/webhooks/courier/steadfast', ['notification_type' => 'delivery_status', 'consignment_id' => 1424107, 'status' => 'delivered'], ['Authorization' => 'Bearer '.str_repeat('t', 40)])->assertOk();

        $order = $this->order->fresh();
        $this->assertSame(['shipped', 'delivered'], [$order->status, $order->courier_status]);
    }

    public function test_refresh_reads_the_status_from_steadfast(): void
    {
        $this->order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1424107', 'courier_status' => 'in_review', 'status' => 'shipped']);
        Http::fake(['*/status_by_cid/1424107' => Http::response(['status' => 200, 'delivery_status' => 'hold'])]);

        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier/refresh")->assertSessionHas('success', 'Steadfast status: Hold.');
        $this->assertSame('hold', $this->order->fresh()->courier_status);
    }

    public function test_pathao_booking_needs_a_city_and_zone(): void
    {
        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'pathao']))
            ->assertSessionHasErrors(['city_id', 'zone_id']);
    }

    public function test_pathao_booking_and_token_reuse(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['token_type' => 'Bearer', 'expires_in' => 432000, 'access_token' => 'PATHAO-TOKEN']),
            '*/aladdin/api/v1/orders' => Http::response(['code' => 200, 'type' => 'success', 'data' => ['consignment_id' => 'DL121224VS8TTJ', 'merchant_order_id' => '26112400001', 'order_status' => 'Pending', 'delivery_fee' => 60]]),
            '*/aladdin/api/v1/city-list' => Http::response(['data' => ['data' => [['city_id' => 1, 'city_name' => 'Dhaka']]]]),
        ]);

        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'pathao', 'city_id' => 1, 'zone_id' => 298, 'weight' => 1, 'delivery_type' => 48]))
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->getJson('/admin/couriers/pathao/locations')->assertOk()->assertJson([['id' => 1, 'name' => 'Dhaka']]);

        Http::assertSentCount(3); // one token for both calls
        Http::assertSent(fn ($r) => str_contains($r->url(), 'courier-api-sandbox.pathao.com/aladdin/api/v1/orders')
            && $r->header('Authorization')[0] === 'Bearer PATHAO-TOKEN'
            && $r['store_id'] === 777 && $r['recipient_city'] === 1 && $r['recipient_zone'] === 298
            && $r['amount_to_collect'] === 5060 && $r['item_weight'] == 1 && $r['recipient_phone'] === '01712345678');

        $order = $this->order->fresh();
        $this->assertSame(['pathao', 'DL121224VS8TTJ', 'pending', 60.0, 'shipped'], [$order->courier, $order->courier_consignment_id, $order->courier_status, $order->courier_fee, $order->status]);
        $this->assertStringContainsString('consignment_id=DL121224VS8TTJ', $order->courier_tracking_url);
    }

    public function test_pathao_webhook(): void
    {
        $this->order->update(['courier' => 'pathao', 'courier_consignment_id' => 'DL121224VS8TTJ', 'status' => 'shipped']);
        $secret = ['X-PATHAO-Signature' => str_repeat('p', 40)];

        $this->postJson('/webhooks/courier/pathao', ['event' => 'webhook_integration'], $secret)
            ->assertStatus(202)
            ->assertHeader('X-Pathao-Merchant-Webhook-Integration-Secret', Pathao::WEBHOOK_INTEGRATION_SECRET);
        $this->postJson('/webhooks/courier/pathao', ['event' => 'order.delivered', 'consignment_id' => 'DL121224VS8TTJ'], ['X-PATHAO-Signature' => 'nope'])->assertStatus(401);

        $this->postJson('/webhooks/courier/pathao', ['event' => 'order.in-transit', 'consignment_id' => 'DL121224VS8TTJ'], $secret)->assertStatus(202);
        $this->assertSame(['in_transit', 'shipped'], [$this->order->fresh()->courier_status, $this->order->fresh()->status]);

        $this->postJson('/webhooks/courier/pathao', ['event' => 'order.delivered', 'consignment_id' => 'DL121224VS8TTJ', 'merchant_order_id' => '26112400001', 'delivery_fee' => 70], $secret)->assertStatus(202);
        $order = $this->order->fresh();
        $this->assertSame(['delivered', 'delivered', 'paid', 70.0], [$order->courier_status, $order->status, $order->payment_status, $order->courier_fee]);
    }

    public function test_an_order_cannot_be_booked_twice_or_when_cancelled(): void
    {
        Http::fake();
        $this->order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1']);
        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'steadfast']))
            ->assertSessionHas('error', 'This order is already with Steadfast.');

        $this->order->update(['courier' => null, 'courier_consignment_id' => null, 'status' => 'cancelled']);
        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'steadfast']))
            ->assertSessionHas('error', 'A cancelled order cannot be shipped.');
        Http::assertNothingSent();
    }

    public function test_disabled_couriers_cannot_be_used(): void
    {
        Setting::put(['steadfast_enabled' => '0']);
        $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/courier", $this->booking(['courier' => 'steadfast']))
            ->assertSessionHasErrors('courier');
        $this->actingAs($this->admin)->get("/admin/orders/{$this->order->id}")
            ->assertInertia(fn (Assert $p) => $p->where('couriers', ['pathao' => 'Pathao']));
    }

    public function test_settings_generate_webhook_tokens_and_hide_courier_secrets(): void
    {
        Setting::put(['steadfast_webhook_token' => '']);
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()
            ->assertDontSee('SF-SECRET')->assertDontSee('CSECRET')
            ->assertInertia(fn (Assert $p) => $p->where('couriers.steadfast.live', true)->where('values.steadfast_secret_key', ''));
        $this->assertSame(40, strlen(Setting::get('steadfast_webhook_token')));

        Setting::put(['steadfast_api_key' => '', 'steadfast_enabled' => '0']);
        $this->actingAs($this->admin)->put('/admin/settings', array_merge(Setting::all_settings(), ['steadfast_enabled' => '1', 'steadfast_api_key' => '']))
            ->assertSessionHasErrors('steadfast_enabled');
        $this->assertFalse(Setting::enabled('steadfast_enabled'));
    }

    public function test_customers_see_the_tracking_link(): void
    {
        $this->order->update(['courier' => 'steadfast', 'courier_consignment_id' => '1424107', 'courier_tracking_code' => '15BAEB8A', 'courier_status' => 'in_review', 'status' => 'shipped']);

        $this->get('/track-order?order_number=26112400001&phone='.urlencode('+8801712345678'))
            ->assertInertia(fn (Assert $p) => $p->where('order.courier_tracking_url', 'https://steadfast.com.bd/t/15BAEB8A'));
    }
}
