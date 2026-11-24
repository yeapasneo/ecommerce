<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/** SSLCommerz hosted checkout (API v4): cards, mobile banking and internet banking. */
class SslCommerz extends Gateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }

    protected function requiredSettings(): array
    {
        return ['sslcommerz_store_id', 'sslcommerz_store_password'];
    }

    protected function base(): string
    {
        return $this->sandbox() ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
    }

    public function initiate(Order $order): string
    {
        $order->loadMissing('items');
        $tranId = $order->order_number.'-'.now()->format('His');

        $response = $this->http()->asForm()->post($this->base().'/gwprocess/v4/api.php', [
            'store_id' => $this->setting('store_id'),
            'store_passwd' => $this->setting('store_password'),
            'total_amount' => $this->amount($order),
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => $this->callbackUrl($order, 'success'),
            'fail_url' => $this->callbackUrl($order, 'fail'),
            'cancel_url' => $this->callbackUrl($order, 'cancel'),
            'ipn_url' => route('payment.ipn', ['gateway' => 'sslcommerz']),
            'cus_name' => trim($order->first_name.' '.$order->last_name),
            'cus_email' => $order->email ?: 'customer@'.parse_url(config('app.url'), PHP_URL_HOST),
            'cus_add1' => $order->address,
            'cus_city' => $order->city,
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $order->phone,
            'shipping_method' => 'NO',
            'num_of_item' => $order->items->sum('quantity'),
            'product_name' => $this->productSummary($order),
            'product_category' => 'General',
            'product_profile' => 'general',
            'value_a' => $order->order_number,
        ]);

        if ($response->json('status') !== 'SUCCESS' || ! $response->json('GatewayPageURL')) {
            $this->fail($response->json('failedreason') ?: 'SSLCommerz could not start the payment.', ['order' => $order->order_number]);
        }
        $order->update(['gateway_ref' => $tranId]);

        return $response->json('GatewayPageURL');
    }

    public function verify(Order $order, Request $request, string $result): PaymentResult
    {
        if ($result === 'cancel') {
            return PaymentResult::cancelled();
        }
        if ($result === 'fail' || ! $request->filled('val_id')) {
            return PaymentResult::failed($request->input('error') ?: 'The payment failed.');
        }

        return $this->validate($order, (string) $request->input('val_id'));
    }

    /** Server-to-server notification; also used when the browser never returns. */
    public function ipn(Request $request): ?Order
    {
        $order = Order::where('order_number', (string) $request->input('value_a'))->first();
        if (! $order || ! $request->filled('val_id')) {
            return null;
        }
        $result = $this->validate($order, (string) $request->input('val_id'));
        if ($result->isPaid()) {
            $order->markPaid($this->key(), $result->transactionId, $result->reference);
        }

        return $order;
    }

    protected function validate(Order $order, string $valId): PaymentResult
    {
        $response = $this->http()->get($this->base().'/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $this->setting('store_id'),
            'store_passwd' => $this->setting('store_password'),
            'format' => 'json',
        ]);
        $data = $response->json() ?? [];

        if (! in_array($data['status'] ?? null, ['VALID', 'VALIDATED'], true)) {
            return PaymentResult::failed('SSLCommerz did not confirm the payment.');
        }
        if (($data['tran_id'] ?? null) !== $order->gateway_ref || ! $this->amountCovers($order, $data['amount'] ?? null)) {
            return PaymentResult::failed('The payment details did not match this order.');
        }

        return PaymentResult::paid($data['bank_tran_id'] ?? $valId, $data['tran_id']);
    }
}
