<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'RAM', 'slug' => 'ram'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Test RAM', 'slug' => 'test-ram', 'sku' => 'RAM-1',
            'price' => 5000, 'stock' => 10, 'stock_status' => 'in_stock',
        ]);
        Setting::put([
            'sslcommerz_enabled' => '1', 'sslcommerz_store_id' => 'store1', 'sslcommerz_store_password' => 'pass1',
            'bkash_enabled' => '1', 'bkash_app_key' => 'key', 'bkash_app_secret' => 'secret', 'bkash_username' => 'user', 'bkash_password' => 'pw',
        ]);
    }

    /** Forget the current session, like a different browser. */
    protected function newBrowser(): void
    {
        $this->flushSession();
        $this->app['session']->setId(Str::random(40));
        $this->withCookie(config('session.cookie'), Str::random(40));
    }

    protected function placeOrder(string $method): Order
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post('/checkout', [
            'first_name' => 'Test', 'phone' => '01700000001', 'address' => 'Road 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => $method, 'agree' => true,
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail(); // 5000 + 60 delivery
    }

    public function test_checkout_offers_only_enabled_gateways(): void
    {
        Setting::put(['eps_enabled' => '1']); // enabled but no credentials → not offered
        $this->post('/cart', ['product_id' => $this->product->id]);

        $this->get('/checkout')->assertInertia(fn ($page) => $page->where('paymentMethods', [
            'cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'sslcommerz' => 'Card / Mobile Banking (SSLCommerz)',
        ]));
        $this->post('/checkout', ['payment_method' => 'eps'])->assertSessionHasErrors('payment_method');
    }

    public function test_sslcommerz_payment_is_verified_before_the_order_is_paid(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc']),
            'sandbox.sslcommerz.com/validator/*' => fn ($request) => Http::response([
                'status' => 'VALID',
                'tran_id' => Order::latest('id')->value('gateway_ref'),
                'amount' => '5060.00',
                'bank_tran_id' => 'BANK123',
            ]),
        ]);

        $this->post('/cart', ['product_id' => $this->product->id]);
        $this->post('/checkout', [
            'first_name' => 'Test', 'phone' => '01700000001', 'address' => 'Road 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'sslcommerz', 'agree' => true,
        ])->assertRedirect();
        $order = Order::latest('id')->first();

        $this->get("/payment/{$order->order_number}/pay")->assertRedirect('https://sandbox.sslcommerz.com/pay/abc');
        $order->refresh();
        $this->assertStringStartsWith($order->order_number.'-', $order->gateway_ref);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'gwprocess') && $r['total_amount'] === '5060.00' && $r['store_passwd'] === 'pass1');

        // Gateway posts back cross-site: no session cookie, no CSRF token.
        $response = $this->call('POST', "/payment/sslcommerz/success/{$order->order_number}", ['val_id' => 'VAL1', 'tran_id' => $order->gateway_ref], [], [], []);
        $response->assertRedirect();
        $this->assertStringContainsString('payment=paid', $response->headers->get('Location'));
        $this->assertEmpty(array_filter($response->headers->getCookies(), fn ($c) => $c->getName() === config('session.cookie')), 'callback must not start a session');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('BANK123', $order->transaction_id);
        $this->assertNotNull($order->paid_at);

        // The signed link opens the order page even in a fresh browser session.
        $this->newBrowser();
        $this->get("/checkout/success/{$order->order_number}")->assertNotFound();
        $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn ($p) => $p->component('OrderSuccess')->where('paymentResult', 'paid'));
    }

    public function test_sslcommerz_amount_mismatch_is_not_paid(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc']),
            'sandbox.sslcommerz.com/validator/*' => fn () => Http::response(['status' => 'VALID', 'tran_id' => Order::latest('id')->value('gateway_ref'), 'amount' => '10.00']),
        ]);
        $order = $this->placeOrder('sslcommerz');
        $this->get("/payment/{$order->order_number}/pay");

        $this->post("/payment/sslcommerz/success/{$order->order_number}", ['val_id' => 'VAL1'])->assertRedirect();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_forged_success_callback_without_gateway_confirmation_is_rejected(): void
    {
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'INVALID_TRANSACTION'])]);
        $order = Order::create([
            'order_number' => '99999999999', 'first_name' => 'X', 'phone' => '1', 'address' => 'A', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'sslcommerz', 'payment_status' => 'unpaid', 'status' => 'pending',
            'subtotal' => 100, 'delivery_charge' => 0, 'discount' => 0, 'total' => 100, 'gateway_ref' => 'T1',
        ]);

        $location = $this->post('/payment/sslcommerz/success/99999999999', ['val_id' => 'FAKE', 'status' => 'VALID'])->headers->get('Location');
        $this->assertStringContainsString('payment=failed', $location);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_sslcommerz_ipn_marks_the_order_paid_once(): void
    {
        $order = $this->placeOrder('sslcommerz');
        $order->update(['gateway_ref' => $order->order_number.'-120000']);
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'VALID', 'tran_id' => $order->order_number.'-120000', 'amount' => '5060.00', 'bank_tran_id' => 'B1'])]);

        $this->post('/payment/sslcommerz/ipn', ['val_id' => 'V', 'value_a' => $order->order_number, 'status' => 'VALID'])->assertOk();
        $this->post('/payment/sslcommerz/ipn', ['val_id' => 'V', 'value_a' => $order->order_number, 'status' => 'VALID'])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, $order->histories()->where('comment', 'like', 'Payment received%')->count());
    }

    public function test_bkash_tokenized_checkout(): void
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'TOKEN', 'statusCode' => '0000']),
            '*/create' => Http::response(['statusCode' => '0000', 'paymentID' => 'PAY123', 'bkashURL' => 'https://sandbox.bka.sh/checkout?paymentID=PAY123']),
            '*/execute' => Http::response(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX9', 'amount' => '5060.00']),
        ]);
        $order = $this->placeOrder('bkash');

        $this->get("/payment/{$order->order_number}/pay")->assertRedirect('https://sandbox.bka.sh/checkout?paymentID=PAY123');
        $this->assertSame('PAY123', $order->fresh()->gateway_ref);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/create') && $r->header('Authorization')[0] === 'TOKEN' && $r['amount'] === '5060.00');

        // A callback for someone else's payment is ignored.
        $this->get("/payment/bkash/return/{$order->order_number}?paymentID=OTHER&status=success");
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->get("/payment/bkash/return/{$order->order_number}?paymentID=PAY123&status=success")->assertRedirect();
        $order->refresh();
        $this->assertSame(['paid', 'TRX9', 'bkash'], [$order->payment_status, $order->transaction_id, $order->payment_method]);
    }

    public function test_bkash_cancel_leaves_order_unpaid_and_offers_retry(): void
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'TOKEN']),
            '*/create' => Http::response(['statusCode' => '0000', 'paymentID' => 'PAY1', 'bkashURL' => 'https://sandbox.bka.sh/x']),
        ]);
        $order = $this->placeOrder('bkash');
        $this->get("/payment/{$order->order_number}/pay");

        $location = $this->get("/payment/bkash/return/{$order->order_number}?paymentID=PAY1&status=cancel")->headers->get('Location');
        $this->assertStringContainsString('payment=cancelled', $location);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->get($location)->assertInertia(fn ($p) => $p->where('payWith.bkash', 'bKash')->where('payWith.sslcommerz', 'Card / Mobile Banking (SSLCommerz)'));
    }

    public function test_gateway_errors_return_to_the_order_page(): void
    {
        Http::fake(['*/gwprocess/*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Store Credential Error'])]);
        $order = $this->placeOrder('sslcommerz');

        $this->get("/payment/{$order->order_number}/pay")
            ->assertRedirect("/checkout/success/{$order->order_number}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Store Credential Error'));
    }

    public function test_strangers_cannot_start_payment_for_an_order(): void
    {
        $order = $this->placeOrder('sslcommerz');
        $this->newBrowser();

        $this->get("/payment/{$order->order_number}/pay")->assertNotFound();
    }

    public function test_gateway_needs_credentials_before_it_can_be_enabled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/settings', array_merge(Setting::all_settings(), ['nagad_enabled' => '1', 'nagad_merchant_id' => '683']))
            ->assertSessionHasErrors('nagad_enabled');
        $this->assertFalse(Setting::enabled('nagad_enabled'));
        $this->assertSame('683', Setting::get('nagad_merchant_id'));
    }

    public function test_nagad_encrypts_signs_and_verifies(): void
    {
        $keyPair = function () {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            return [$private, openssl_pkey_get_details($key)['key']];
        };
        [$nagadPrivate, $nagadPublic] = $keyPair();
        [$merchantPrivate, $merchantPublic] = $keyPair();
        // Admins often paste keys without the BEGIN/END lines.
        $bare = fn ($pem) => preg_replace('/-----[^-]+-----|\s+/', '', $pem);
        Setting::put([
            'nagad_enabled' => '1', 'nagad_merchant_id' => '683002007104225', 'nagad_merchant_number' => '01711111111',
            'nagad_public_key' => $bare($nagadPublic), 'nagad_private_key' => $merchantPrivate,
        ]);

        $seen = [];
        Http::fake([
            '*/check-out/initialize/*' => function ($request) use ($nagadPrivate, $merchantPublic, $merchantPrivate, &$seen) {
                openssl_private_decrypt(base64_decode($request['sensitiveData']), $plain, $nagadPrivate);
                $seen['init'] = json_decode($plain, true);
                $seen['signature_ok'] = openssl_verify($plain, base64_decode($request['signature']), openssl_pkey_get_details(openssl_pkey_get_private($merchantPrivate))['key'], OPENSSL_ALGO_SHA256) === 1;
                openssl_public_encrypt(json_encode(['paymentReferenceId' => 'REF1', 'challenge' => 'CH1']), $enc, $merchantPublic);

                return Http::response(['sensitiveData' => base64_encode($enc), 'signature' => 'x']);
            },
            '*/check-out/complete/REF1' => function ($request) use ($nagadPrivate, &$seen) {
                openssl_private_decrypt(base64_decode($request['sensitiveData']), $plain, $nagadPrivate);
                $seen['complete'] = json_decode($plain, true);

                return Http::response(['status' => 'Success', 'callBackUrl' => 'https://sandbox.mynagad.com/pay/REF1']);
            },
            '*/verify/payment/REF1' => fn () => Http::response(['status' => 'Success', 'orderId' => Order::latest('id')->value('gateway_ref'), 'amount' => '5060', 'issuerPaymentRefNo' => 'NGD777']),
        ]);

        $order = $this->placeOrder('nagad');
        $this->get("/payment/{$order->order_number}/pay")->assertRedirect('https://sandbox.mynagad.com/pay/REF1');
        $order->refresh();

        $this->assertTrue($seen['signature_ok']);
        $this->assertSame($order->gateway_ref, $seen['init']['orderId']);
        $this->assertLessThanOrEqual(20, strlen($order->gateway_ref));
        $this->assertSame(['5060.00', 'CH1', '050'], [$seen['complete']['amount'], $seen['complete']['challenge'], $seen['complete']['currencyCode']]);

        $this->get("/payment/nagad/return/{$order->order_number}?payment_ref_id=REF1&status=Success")->assertRedirect();
        $this->assertSame(['paid', 'NGD777'], [$order->fresh()->payment_status, $order->fresh()->transaction_id]);
    }

    public function test_eps_signs_requests_and_verifies(): void
    {
        Setting::put([
            'eps_enabled' => '1', 'eps_merchant_id' => 'M1', 'eps_store_id' => 'S1', 'eps_username' => 'epsuser', 'eps_password' => 'epspass', 'eps_hash_key' => 'HASHKEY',
        ]);
        Http::fake([
            '*/Auth/GetToken' => Http::response(['token' => 'EPSTOKEN']),
            '*/EPSEngine/InitializeEPS' => Http::response(['TransactionId' => 'EPS1', 'RedirectURL' => 'https://sandboxpgapi.eps.com.bd/pay/EPS1']),
            '*/EPSEngine/CheckMerchantTransactionStatus*' => Http::response(['Status' => 'Success', 'TotalAmount' => '5060.00', 'EpsTransactionId' => 'EPS-TRX']),
        ]);
        $order = $this->placeOrder('eps');

        $this->get("/payment/{$order->order_number}/pay")->assertRedirect('https://sandboxpgapi.eps.com.bd/pay/EPS1');
        $tx = $order->fresh()->gateway_ref;
        $hash = fn ($v) => base64_encode(hash_hmac('sha512', $v, 'HASHKEY', true));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/Auth/GetToken') && $r->header('x-hash')[0] === $hash('epsuser'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/InitializeEPS') && $r->header('x-hash')[0] === $hash($tx) && $r->header('Authorization')[0] === 'Bearer EPSTOKEN' && $r['merchantTransactionId'] === $tx);

        $this->get("/payment/eps/success/{$order->order_number}")->assertRedirect();
        $this->assertSame(['paid', 'EPS-TRX'], [$order->fresh()->payment_status, $order->fresh()->transaction_id]);
    }
}
