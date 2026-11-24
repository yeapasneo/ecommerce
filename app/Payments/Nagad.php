<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Nagad Remote Payment Gateway (API v-0.2.0). Sensitive data is RSA-encrypted with
 * Nagad's public key and signed with the merchant's private key.
 */
class Nagad extends Gateway
{
    public function key(): string
    {
        return 'nagad';
    }

    protected function requiredSettings(): array
    {
        return ['nagad_merchant_id', 'nagad_merchant_number', 'nagad_public_key', 'nagad_private_key'];
    }

    protected function base(): string
    {
        return $this->sandbox()
            ? 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'
            : 'https://api.mynagad.com/api/dfs';
    }

    protected function headers(): array
    {
        return [
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
        ];
    }

    public function initiate(Order $order): string
    {
        $merchantId = $this->setting('merchant_id');
        $orderId = $order->order_number.'N'.now()->format('His'); // alphanumeric, ≤ 20 chars, unique per attempt
        $dateTime = now('Asia/Dhaka')->format('YmdHis');

        $init = $this->json(['merchantId' => $merchantId, 'datetime' => $dateTime, 'orderId' => $orderId, 'challenge' => bin2hex(random_bytes(20))]);
        $response = $this->http()->withHeaders($this->headers())
            ->post($this->base()."/check-out/initialize/{$merchantId}/{$orderId}?locale=EN", [
                'accountNumber' => $this->setting('merchant_number'),
                'dateTime' => $dateTime,
                'sensitiveData' => $this->encrypt($init),
                'signature' => $this->sign($init),
            ]);
        if (! $response->json('sensitiveData')) {
            $this->fail('Nagad could not start the payment: '.($response->json('message') ?? 'unknown error'), ['order' => $order->order_number]);
        }
        $reply = json_decode($this->decrypt($response->json('sensitiveData')), true) ?? [];
        if (empty($reply['paymentReferenceId']) || empty($reply['challenge'])) {
            $this->fail('Nagad returned an unexpected reply.');
        }

        $payment = $this->json([
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'currencyCode' => '050',
            'amount' => $this->amount($order),
            'challenge' => $reply['challenge'],
        ]);
        $complete = $this->http()->withHeaders($this->headers())
            ->post($this->base().'/check-out/complete/'.$reply['paymentReferenceId'], [
                'sensitiveData' => $this->encrypt($payment),
                'signature' => $this->sign($payment),
                'merchantCallbackURL' => $this->callbackUrl($order),
                'additionalMerchantInfo' => ['order' => $order->order_number],
            ]);
        if ($complete->json('status') !== 'Success' || ! $complete->json('callBackUrl')) {
            $this->fail('Nagad could not start the payment: '.($complete->json('message') ?? 'unknown error'), ['order' => $order->order_number]);
        }
        $order->update(['gateway_ref' => $orderId]);

        return $complete->json('callBackUrl');
    }

    public function verify(Order $order, Request $request, string $result): PaymentResult
    {
        $status = (string) $request->query('status');
        if (in_array($status, ['Aborted', 'Cancelled'], true)) {
            return PaymentResult::cancelled();
        }
        $refId = (string) $request->query('payment_ref_id');
        if ($refId === '') {
            return PaymentResult::failed('Nagad did not return a payment reference.');
        }

        $data = $this->http()->withHeaders($this->headers())->get($this->base().'/verify/payment/'.rawurlencode($refId))->json() ?? [];
        if (($data['status'] ?? null) !== 'Success') {
            return PaymentResult::failed('Nagad did not confirm the payment.');
        }
        if (($data['orderId'] ?? null) !== $order->gateway_ref || ! $this->amountCovers($order, $data['amount'] ?? null)) {
            return PaymentResult::failed('The Nagad payment did not match this order.');
        }

        return PaymentResult::paid($data['issuerPaymentRefNo'] ?? $refId, $refId);
    }

    protected function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    protected function encrypt(string $data): string
    {
        $key = openssl_pkey_get_public($this->pem($this->setting('public_key'), 'PUBLIC KEY'));
        if (! $key || ! openssl_public_encrypt($data, $encrypted, $key, OPENSSL_PKCS1_PADDING)) {
            $this->fail('The Nagad public key is invalid.');
        }

        return base64_encode($encrypted);
    }

    protected function sign(string $data): string
    {
        if (! openssl_sign($data, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            $this->fail('Could not sign the Nagad request.');
        }

        return base64_encode($signature);
    }

    protected function decrypt(string $data): string
    {
        if (! openssl_private_decrypt(base64_decode($data), $decrypted, $this->privateKey(), OPENSSL_PKCS1_PADDING)) {
            $this->fail('Could not read the Nagad reply. Check the merchant private key.');
        }

        return $decrypted;
    }

    protected function privateKey()
    {
        $raw = $this->setting('private_key');
        $key = openssl_pkey_get_private($this->pem($raw, 'PRIVATE KEY')) ?: openssl_pkey_get_private($this->pem($raw, 'RSA PRIVATE KEY'));
        if (! $key) {
            $this->fail('The Nagad merchant private key is invalid.');
        }

        return $key;
    }

    /** Accept keys pasted with or without the -----BEGIN … ----- lines. */
    protected function pem(?string $key, string $type): string
    {
        $key = trim((string) $key);
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN {$type}-----\n".chunk_split(preg_replace('/\s+/', '', $key), 64, "\n")."-----END {$type}-----";
    }
}
