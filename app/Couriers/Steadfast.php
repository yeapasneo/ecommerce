<?php

namespace App\Couriers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Steadfast Courier (Packzy) merchant API v1. */
class Steadfast extends Courier
{
    public const BASE = 'https://portal.packzy.com/api/v1';

    public function key(): string
    {
        return 'steadfast';
    }

    public function label(): string
    {
        return 'Steadfast';
    }

    protected function requiredSettings(): array
    {
        return ['steadfast_api_key', 'steadfast_secret_key'];
    }

    protected function api()
    {
        return $this->http()->withHeaders([
            'Api-Key' => (string) $this->setting('api_key'),
            'Secret-Key' => (string) $this->setting('secret_key'),
        ]);
    }

    public function send(Order $order, array $input): void
    {
        $response = $this->api()->post(self::BASE.'/create_order', [
            'invoice' => $order->order_number,
            'recipient_name' => Str::limit($input['recipient_name'], 100, ''),
            'recipient_phone' => self::phone($input['recipient_phone']),
            'recipient_address' => Str::limit($input['recipient_address'], 250, ''),
            'cod_amount' => round((float) $input['cod_amount'], 2),
            'note' => $input['note'] ?? null,
            'item_description' => Str::limit($order->items->map(fn ($i) => "{$i->quantity} x {$i->name}")->implode(', '), 250, ''),
        ]);

        $consignment = $response->json('consignment');
        if ((int) $response->json('status') !== 200 || empty($consignment['consignment_id'])) {
            $this->fail('Steadfast rejected the parcel: '.$this->errorText($response, 'unknown error'), $response);
        }

        $this->booked($order, [
            'courier_consignment_id' => (string) $consignment['consignment_id'],
            'courier_tracking_code' => $consignment['tracking_code'] ?? null,
            'courier_status' => $consignment['status'] ?? 'in_review',
            'courier_cod' => (float) ($consignment['cod_amount'] ?? $input['cod_amount']),
        ]);
    }

    public function fetchStatus(Order $order): ?string
    {
        $response = $this->api()->get(self::BASE.'/status_by_cid/'.rawurlencode($order->courier_consignment_id));
        if ((int) $response->json('status') !== 200) {
            $this->fail('Could not get the Steadfast status: '.$this->errorText($response, 'unknown error'), $response);
        }

        return $response->json('delivery_status');
    }

    public function balance(): float
    {
        $response = $this->api()->get(self::BASE.'/get_balance');
        if ((int) $response->json('status') !== 200) {
            $this->fail('Steadfast did not accept the API key and secret key.', $response);
        }

        return (float) $response->json('current_balance');
    }

    public function trackingUrl(Order $order): ?string
    {
        return $order->courier_tracking_code ? 'https://steadfast.com.bd/t/'.rawurlencode($order->courier_tracking_code) : null;
    }

    /**
     * Steadfast sends delivery_status and tracking_update notifications with
     * "Authorization: Bearer <token>", where the token is the one set in both panels.
     */
    public function webhook(Request $request)
    {
        $token = (string) $this->setting('webhook_token');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        $order = Order::where('courier', 'steadfast')
            ->where(fn ($q) => $q->where('courier_consignment_id', (string) $request->input('consignment_id'))
                ->orWhere('order_number', (string) $request->input('invoice')))
            ->first();
        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'Unknown consignment.'], 404);
        }

        if ($request->input('notification_type') === 'delivery_status' && $request->filled('status')) {
            $fee = $request->input('delivery_charge');
            $this->applyStatus($order, (string) $request->input('status'), is_numeric($fee) ? (float) $fee : null);
        } elseif ($request->input('notification_type') === 'tracking_update' && $request->filled('tracking_message')) {
            $order->histories()->create(['status' => $order->status, 'comment' => 'Steadfast: '.Str::limit((string) $request->input('tracking_message'), 500)]);
        }

        return response()->json(['status' => 'success', 'message' => 'Webhook received successfully.']);
    }
}
