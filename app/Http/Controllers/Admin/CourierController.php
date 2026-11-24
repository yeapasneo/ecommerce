<?php

namespace App\Http\Controllers\Admin;

use App\Couriers\Courier;
use App\Couriers\CourierException;
use App\Couriers\CourierManager;
use App\Couriers\Pathao;
use App\Couriers\Steadfast;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CourierController extends Controller
{
    public function __construct(protected CourierManager $couriers) {}

    /** Book the order's parcel with a courier. */
    public function send(Request $request, Order $order)
    {
        $data = $request->validate([
            'courier' => ['required', Rule::in(array_keys($this->couriers->enabled()))],
            'recipient_name' => ['required', 'string', 'min:3', 'max:100'],
            'recipient_phone' => ['required', 'string', function ($attr, $value, $fail) {
                if (! preg_match('/^01[3-9]\d{8}$/', Courier::phone($value))) {
                    $fail('Enter a valid Bangladeshi mobile number (01XXXXXXXXX).');
                }
            }],
            'recipient_address' => ['required', 'string', 'min:10', 'max:250'],
            'cod_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:250'],
            'city_id' => ['required_if:courier,pathao', 'nullable', 'integer'],
            'zone_id' => ['required_if:courier,pathao', 'nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'weight' => ['nullable', 'numeric', 'min:0.5', 'max:10'],
            'delivery_type' => ['nullable', Rule::in([48, 12])],
        ], [
            'city_id.required_if' => 'Choose the delivery city.',
            'zone_id.required_if' => 'Choose the delivery zone.',
        ]);

        if ($order->courier_consignment_id) {
            return back()->with('error', 'This order is already with '.$this->couriers->driver($order->courier)->label().'.');
        }
        if ($order->status === 'cancelled') {
            return back()->with('error', 'A cancelled order cannot be shipped.');
        }

        $courier = $this->couriers->driver($data['courier']);
        try {
            $courier->send($order->load('items'), $data);
        } catch (CourierException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error("[{$courier->key()}] booking failed", ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return back()->with('error', "{$courier->label()} is not responding. Please try again in a moment.");
        }

        return back()->with('success', "Parcel booked with {$courier->label()}. Consignment {$order->courier_consignment_id}.");
    }

    public function refresh(Order $order)
    {
        abort_unless($order->courier && $order->courier_consignment_id, 404);
        $courier = $this->couriers->driver($order->courier);
        try {
            $status = $courier->refresh($order);
        } catch (Throwable $e) {
            return back()->with('error', $e instanceof CourierException ? $e->getMessage() : "{$courier->label()} is not responding.");
        }

        return back()->with('success', "{$courier->label()} status: ".Courier::statusLabel($status).'.');
    }

    /** Check the saved credentials (Settings → Couriers). */
    public function test(string $courier)
    {
        abort_unless(isset(CourierManager::COURIERS[$courier]), 404);
        try {
            if ($courier === 'steadfast') {
                $balance = app(Steadfast::class)->balance();

                return response()->json(['ok' => true, 'message' => 'Connected. Current balance: ৳'.number_format($balance, 2)]);
            }
            $stores = collect(app(Pathao::class)->stores())->map(fn ($s) => ['id' => $s['store_id'], 'name' => $s['store_name'] ?? ('Store '.$s['store_id'])])->values();

            return response()->json(['ok' => true, 'message' => 'Connected. '.$stores->count().' pickup store(s) found.', 'stores' => $stores]);
        } catch (CourierException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not reach the courier: '.$e->getMessage()], 422);
        }
    }

    /** Pathao city / zone / area lists for the booking form. */
    public function pathaoLocations(Request $request, Pathao $pathao)
    {
        try {
            $items = match (true) {
                $request->filled('zone') => collect($pathao->areas((int) $request->query('zone')))->map(fn ($a) => ['id' => $a['area_id'], 'name' => $a['area_name']]),
                $request->filled('city') => collect($pathao->zones((int) $request->query('city')))->map(fn ($z) => ['id' => $z['zone_id'], 'name' => $z['zone_name']]),
                default => collect($pathao->cities())->map(fn ($c) => ['id' => $c['city_id'], 'name' => $c['city_name']]),
            };
        } catch (Throwable $e) {
            return response()->json(['message' => $e instanceof CourierException ? $e->getMessage() : 'Could not reach Pathao.'], 422);
        }

        return response()->json($items->values());
    }
}
