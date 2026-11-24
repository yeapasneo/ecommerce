<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/** EPS (Easy Payment System) hosted checkout, API v1. Requests carry an HMAC-SHA512 "x-hash" header. */
class Eps extends Gateway
{
    public function key(): string
    {
        return 'eps';
    }

    protected function requiredSettings(): array
    {
        return ['eps_merchant_id', 'eps_store_id', 'eps_username', 'eps_password', 'eps_hash_key'];
    }

    protected function base(): string
    {
        return $this->sandbox() ? 'https://sandboxpgapi.eps.com.bd/v1' : 'https://pgapi.eps.com.bd/v1';
    }

    protected function hash(string $value): string
    {
        return base64_encode(hash_hmac('sha512', $value, (string) $this->setting('hash_key'), true));
    }

    protected function token(): string
    {
        $username = (string) $this->setting('username');
        $response = $this->http()->withHeaders(['x-hash' => $this->hash($username)])
            ->post($this->base().'/Auth/GetToken', ['userName' => $username, 'password' => $this->setting('password')]);
        $token = $response->json('token');
        if (! $token) {
            $this->fail('EPS rejected the merchant credentials: '.($response->json('errorMessage') ?? 'unknown error'));
        }

        return $token;
    }

    public function initiate(Order $order): string
    {
        $order->loadMissing('items');
        $transactionId = $this->attemptId($order);

        $response = $this->http()
            ->withHeaders(['x-hash' => $this->hash($transactionId)])
            ->withToken($this->token())
            ->post($this->base().'/EPSEngine/InitializeEPS', [
                'merchantId' => $this->setting('merchant_id'),
                'storeId' => $this->setting('store_id'),
                'CustomerOrderId' => $order->order_number,
                'merchantTransactionId' => $transactionId,
                'transactionTypeId' => 1, // web
                'totalAmount' => (float) $this->amount($order),
                'successUrl' => $this->callbackUrl($order, 'success'),
                'failUrl' => $this->callbackUrl($order, 'fail'),
                'cancelUrl' => $this->callbackUrl($order, 'cancel'),
                'customerName' => trim($order->first_name.' '.$order->last_name),
                'customerEmail' => $order->email ?: 'customer@'.parse_url(config('app.url'), PHP_URL_HOST),
                'customerAddress' => $order->address,
                'customerCity' => $order->city,
                'customerState' => $order->city,
                'customerPostcode' => '1000',
                'customerCountry' => 'BD',
                'customerPhone' => $order->phone,
                'productName' => $this->productSummary($order),
                'productProfile' => 'general',
                'productCategory' => 'General',
                'noOfItem' => (string) $order->items->sum('quantity'),
            ]);

        if (! $response->json('RedirectURL')) {
            $this->fail('EPS could not start the payment: '.($response->json('ErrorMessage') ?? 'unknown error'), ['order' => $order->order_number]);
        }
        $order->update(['gateway_ref' => $transactionId]);

        return $response->json('RedirectURL');
    }

    public function verify(Order $order, Request $request, string $result): PaymentResult
    {
        if ($result === 'cancel') {
            return PaymentResult::cancelled();
        }
        if (! $order->gateway_ref) {
            return PaymentResult::failed('No EPS payment was started for this order.');
        }

        $transactionId = $order->gateway_ref;
        $data = $this->http()
            ->withHeaders(['x-hash' => $this->hash($transactionId)])
            ->withToken($this->token())
            ->get($this->base().'/EPSEngine/CheckMerchantTransactionStatus', ['merchantTransactionId' => $transactionId])
            ->json() ?? [];

        if (strcasecmp((string) ($data['Status'] ?? ''), 'Success') !== 0) {
            return $result === 'fail' ? PaymentResult::failed('The EPS payment failed.') : PaymentResult::failed('EPS did not confirm the payment.');
        }
        if (! $this->amountCovers($order, $data['TotalAmount'] ?? null)) {
            return PaymentResult::failed('The EPS amount did not match this order.');
        }

        return PaymentResult::paid($data['EpsTransactionId'] ?? $data['TransactionId'] ?? $transactionId, $transactionId);
    }
}
