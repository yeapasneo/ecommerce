<?php

namespace App\Couriers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Pathao Courier merchant API ("aladdin" v1). */
class Pathao extends Courier
{
    /** Pathao asks every webhook response to carry this header (from their integration docs). */
    public const WEBHOOK_INTEGRATION_SECRET = 'f3992ecc-59da-4cbe-a049-a13da2018d51';

    public function key(): string
    {
        return 'pathao';
    }

    public function label(): string
    {
        return 'Pathao';
    }

    protected function requiredSettings(): array
    {
        return ['pathao_client_id', 'pathao_client_secret', 'pathao_username', 'pathao_password'];
    }

    protected function base(): string
    {
        return Setting::enabled('pathao_sandbox') ? 'https://courier-api-sandbox.pathao.com' : 'https://api-hermes.pathao.com';
    }

    protected function cacheKey(string $name): string
    {
        return 'pathao:'.$name.':'.md5($this->base().$this->setting('client_id').$this->setting('username'));
    }

    /** Access tokens last for days; reuse one until shortly before it expires. */
    protected function token(): string
    {
        $key = $this->cacheKey('token');
        if ($token = Cache::get($key)) {
            return $token;
        }
        $response = $this->http()->post($this->base().'/aladdin/api/v1/issue-token', [
            'client_id' => $this->setting('client_id'),
            'client_secret' => $this->setting('client_secret'),
            'grant_type' => 'password',
            'username' => $this->setting('username'),
            'password' => $this->setting('password'),
        ]);
        $token = $response->json('access_token');
        if (! $token) {
            $this->fail('Pathao did not accept the client ID, secret, email or password.', $response);
        }
        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3600) - 600));

        return $token;
    }

    protected function api()
    {
        return $this->http()->withToken($this->token());
    }

    protected function list(string $path, string $cacheName): array
    {
        return Cache::remember($this->cacheKey($cacheName), 86400, function () use ($path) {
            $response = $this->api()->get($this->base().$path);
            if (! $response->successful()) {
                $this->fail('Could not load the list from Pathao: '.$this->errorText($response, 'unknown error'), $response);
            }

            return $response->json('data.data') ?? [];
        });
    }

    public function cities(): array
    {
        return $this->list('/aladdin/api/v1/city-list', 'cities');
    }

    public function zones(int $cityId): array
    {
        return $this->list("/aladdin/api/v1/cities/{$cityId}/zone-list", "zones:{$cityId}");
    }

    public function areas(int $zoneId): array
    {
        return $this->list("/aladdin/api/v1/zones/{$zoneId}/area-list", "areas:{$zoneId}");
    }

    public function stores(): array
    {
        $response = $this->api()->get($this->base().'/aladdin/api/v1/stores');
        if (! $response->successful()) {
            $this->fail('Could not load your Pathao stores: '.$this->errorText($response, 'unknown error'), $response);
        }

        return $response->json('data.data') ?? [];
    }

    public function send(Order $order, array $input): void
    {
        $storeId = (int) ($this->setting('store_id') ?: ($this->stores()[0]['store_id'] ?? 0));
        if (! $storeId) {
            $this->fail('Choose your Pathao pickup store in Settings → Couriers first.');
        }

        $response = $this->api()->post($this->base().'/aladdin/api/v1/orders', array_filter([
            'store_id' => $storeId,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => Str::limit($input['recipient_name'], 100, ''),
            'recipient_phone' => self::phone($input['recipient_phone']),
            'recipient_address' => Str::limit($input['recipient_address'], 220, ''),
            'recipient_city' => (int) $input['city_id'],
            'recipient_zone' => (int) $input['zone_id'],
            'recipient_area' => ! empty($input['area_id']) ? (int) $input['area_id'] : null,
            'delivery_type' => (int) ($input['delivery_type'] ?? $this->setting('delivery_type') ?: 48),
            'item_type' => 2, // parcel
            'special_instruction' => $input['note'] ?? null,
            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => (float) ($input['weight'] ?? $this->setting('item_weight') ?: 0.5),
            'amount_to_collect' => (int) round((float) $input['cod_amount']),
            'item_description' => Str::limit($order->items->map(fn ($i) => "{$i->quantity} x {$i->name}")->implode(', '), 250, ''),
        ], fn ($v) => $v !== null && $v !== ''));

        $consignmentId = $response->json('data.consignment_id');
        if (! $response->successful() || ! $consignmentId) {
            $this->fail('Pathao rejected the parcel: '.$this->errorText($response, 'unknown error'), $response);
        }

        $this->booked($order, [
            'courier_consignment_id' => (string) $consignmentId,
            'courier_tracking_code' => (string) $consignmentId,
            'courier_status' => Str::snake(Str::lower((string) $response->json('data.order_status', 'pending'))),
            'courier_fee' => is_numeric($response->json('data.delivery_fee')) ? (float) $response->json('data.delivery_fee') : null,
            'courier_cod' => (float) round((float) $input['cod_amount']),
        ]);
    }

    public function fetchStatus(Order $order): ?string
    {
        $response = $this->api()->get($this->base().'/aladdin/api/v1/orders/'.rawurlencode($order->courier_consignment_id).'/info');
        if (! $response->successful()) {
            $this->fail('Could not get the Pathao status: '.$this->errorText($response, 'unknown error'), $response);
        }

        return $response->json('data.order_status_slug') ?? $response->json('data.order_status');
    }

    public function trackingUrl(Order $order): ?string
    {
        return $order->courier_consignment_id
            ? 'https://merchant.pathao.com/tracking?consignment_id='.rawurlencode($order->courier_consignment_id).'&phone='.rawurlencode(self::phone($order->phone))
            : null;
    }

    /**
     * Pathao signs webhook calls with the secret set in their merchant panel (X-PATHAO-Signature)
     * and expects a 202 that carries the integration header.
     */
    public function webhook(Request $request)
    {
        $reply = fn (array $body, int $status = 202) => response()->json($body, $status)
            ->header('X-Pathao-Merchant-Webhook-Integration-Secret', self::WEBHOOK_INTEGRATION_SECRET);

        $secret = (string) $this->setting('webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-PATHAO-Signature'))) {
            return $reply(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->input('event');
        if ($event === 'webhook_integration') {
            return $reply(['message' => 'Webhook connected.']);
        }

        $order = Order::where('courier', 'pathao')
            ->where(fn ($q) => $q->where('courier_consignment_id', (string) $request->input('consignment_id'))
                ->orWhere('order_number', (string) $request->input('merchant_order_id')))
            ->first();
        if (! $order) {
            return $reply(['message' => 'Unknown consignment.']);
        }

        // e.g. "order.delivered", "order.partial-delivery", "order.in-transit"
        $status = Str::after($event, 'order.');
        if ($status !== '' && $status !== 'created') {
            $fee = $request->input('delivery_fee');
            $this->applyStatus($order, $status, is_numeric($fee) ? (float) $fee : null);
        }

        return $reply(['message' => 'Received.']);
    }
}
