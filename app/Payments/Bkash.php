<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** bKash Tokenized Checkout (v1.2.0-beta). */
class Bkash extends Gateway
{
    public function key(): string
    {
        return 'bkash';
    }

    protected function requiredSettings(): array
    {
        return ['bkash_app_key', 'bkash_app_secret', 'bkash_username', 'bkash_password'];
    }

    protected function base(): string
    {
        return $this->sandbox()
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';
    }

    /** id_token is valid for an hour; reuse it for 50 minutes. */
    protected function token(): string
    {
        $cacheKey = 'bkash_token_'.md5($this->base().$this->setting('app_key'));

        return Cache::remember($cacheKey, 3000, function () {
            $response = $this->http()
                ->withHeaders(['username' => $this->setting('username'), 'password' => $this->setting('password')])
                ->post($this->base().'/token/grant', [
                    'app_key' => $this->setting('app_key'),
                    'app_secret' => $this->setting('app_secret'),
                ]);
            $token = $response->json('id_token');
            if (! $token) {
                $this->fail('bKash rejected the merchant credentials: '.($response->json('statusMessage') ?? $response->json('msg') ?? 'unknown error'));
            }

            return $token;
        });
    }

    protected function api(string $path, array $body)
    {
        return $this->http()
            ->withHeaders(['Authorization' => $this->token(), 'X-APP-Key' => $this->setting('app_key')])
            ->post($this->base().$path, $body);
    }

    public function initiate(Order $order): string
    {
        $response = $this->api('/create', [
            'mode' => '0011',
            'payerReference' => $order->phone,
            'callbackURL' => $this->callbackUrl($order),
            'amount' => $this->amount($order),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
        ]);

        if ($response->json('statusCode') !== '0000' || ! $response->json('bkashURL')) {
            $this->fail('bKash could not start the payment: '.($response->json('statusMessage') ?? 'unknown error'), ['order' => $order->order_number]);
        }
        $order->update(['gateway_ref' => $response->json('paymentID')]);

        return $response->json('bkashURL');
    }

    public function verify(Order $order, Request $request, string $result): PaymentResult
    {
        $paymentId = (string) $request->query('paymentID');
        if ($paymentId === '' || $paymentId !== $order->gateway_ref) {
            return PaymentResult::failed('The bKash payment did not match this order.');
        }

        $status = $request->query('status');
        if ($status === 'cancel') {
            return PaymentResult::cancelled();
        }
        if ($status !== 'success') {
            return PaymentResult::failed('The bKash payment failed.');
        }

        // Execute captures the money. If it times out or was already executed, ask for the status instead.
        try {
            $data = $this->api('/execute', ['paymentID' => $paymentId])->json() ?? [];
        } catch (ConnectionException) {
            $data = [];
        }
        if (($data['transactionStatus'] ?? null) !== 'Completed') {
            $data = $this->api('/payment/status', ['paymentID' => $paymentId])->json() ?? [];
        }

        if (($data['transactionStatus'] ?? null) !== 'Completed') {
            return PaymentResult::failed($data['statusMessage'] ?? 'bKash did not complete the payment.');
        }
        if (! $this->amountCovers($order, $data['amount'] ?? null)) {
            return PaymentResult::failed('The bKash amount did not match this order.');
        }

        return PaymentResult::paid($data['trxID'] ?? null, $paymentId);
    }
}
