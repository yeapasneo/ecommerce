<?php

namespace App\Http\Controllers\Admin;

use App\Couriers\CourierManager;
use App\Http\Controllers\Controller;
use App\Messaging\OrderNotifier;
use App\Messaging\Sms\SmsManager;
use App\Models\BlockedPhone;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Setting;
use App\Services\FraudCheck;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq->where('order_number', 'ilike', "%{$s}%")
                ->orWhere('phone', 'ilike', "%{$s}%")->orWhere('first_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()->paginate($this->perPage())->withQueryString();
        $blocked = BlockedPhone::whereIn('phone', $orders->getCollection()->map(fn ($o) => Phone::normalize($o->phone))->unique())->pluck('phone')->all();
        $orders->getCollection()->each(fn ($o) => $o->setAttribute('flagged', in_array(Phone::normalize($o->phone), $blocked, true)));

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only('search', 'status', 'payment_status', 'from', 'to', 'per_page'),
            'statusCounts' => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'sources' => Order::SOURCES,
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load('items.product:id,slug,image', 'histories', 'user:id,name,email'),
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'couriers' => app(CourierManager::class)->enabled(),
            'validity' => app(FraudCheck::class)->report($order->phone, $order->id),
            'notificationLog' => NotificationLog::where('order_id', $order->id)->latest()->take(30)->get(),
            'courierDefaults' => [
                'weight' => (float) (Setting::get('pathao_item_weight') ?: 0.5),
                'delivery_type' => (int) (Setting::get('pathao_delivery_type') ?: 48),
            ],
        ]);
    }

    public function update(Request $request, Order $order, OrderService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'payment_status' => ['required', Rule::in(Order::PAYMENT_STATUSES)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($order->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()->with('error', 'Cancelled orders cannot be re-opened.');
        }
        $nowPaid = $order->payment_status !== 'paid' && $data['payment_status'] === 'paid';
        $order->update(['payment_status' => $data['payment_status']] + ($nowPaid ? ['paid_at' => now()] : []));
        $service->changeStatus($order, $data['status'], $data['comment'] ?? null);
        if ($nowPaid && $order->status !== 'delivered') {
            app(OrderNotifier::class)->notify($order, 'payment_received');
        }

        return back()->with('success', 'Order updated.');
    }

    public function invoice(Order $order)
    {
        return Inertia::render('Admin/Orders/Invoice', [
            'order' => $order->load('items'),
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Orders/Create', [
            'paymentMethods' => collect(Order::PAYMENT_METHODS)->except('online')->all(),
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'sources' => collect(Order::SOURCES)->except('website')->all(),
            'statuses' => ['pending', 'processing', 'shipped', 'delivered'],
            'deliveryRates' => [
                'inside' => (float) Setting::get('delivery_charge_inside', 60),
                'outside' => (float) Setting::get('delivery_charge_outside', 120),
                'express' => (float) Setting::get('express_delivery_charge', 200),
                'freeOver' => (float) Setting::get('free_delivery_over', 0),
            ],
            'smsReady' => app(SmsManager::class)->isConfigured(),
        ]);
    }

    /** Orders taken by staff (phone, Facebook, in shop). */
    public function store(Request $request, OrderService $service)
    {
        $inShop = $request->input('source') === 'in_shop';
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(Order::SOURCES))],
            'status' => ['required', Rule::in(['pending', 'processing', 'shipped', 'delivered'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => [$inShop ? 'nullable' : 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => [$inShop ? 'nullable' : 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => [$inShop ? 'nullable' : 'required', 'string', 'max:1000'],
            'city' => [$inShop ? 'nullable' : 'required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_method' => ['required', Rule::in(array_keys(Order::DELIVERY_METHODS))],
            'delivery_charge' => ['required', 'numeric', 'min:0', 'max:100000'],
            'discount_type' => ['required', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'payment_status' => ['required', Rule::in(['unpaid', 'paid'])],
            'notify' => ['boolean'],
        ], [
            'items.required' => 'Add at least one product.',
            'items.*.product_id.distinct' => 'A product is listed twice; change its quantity instead.',
        ]);
        if (! $inShop && ! Phone::isValid($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number, e.g. 01712345678.']);
        }

        $admin = $request->user();
        $order = $service->place(
            collect($data['items'])->map(fn ($i) => ['product_id' => $i['product_id'], 'quantity' => $i['quantity'], 'price' => $i['price']])->all(),
            function (float $subtotal) use ($data, $inShop) {
                $value = (float) ($data['discount_value'] ?? 0);
                $discount = $data['discount_type'] === 'percent' ? round($subtotal * min($value, 100) / 100, 2) : min($value, $subtotal);
                $delivery = $data['delivery_method'] === 'store_pickup' ? 0 : (float) $data['delivery_charge'];

                return [
                    'user_id' => $data['user_id'] ?? null,
                    'first_name' => ($data['first_name'] ?? null) ?: 'Walk-in customer',
                    'last_name' => $data['last_name'] ?? null,
                    'phone' => (string) ($data['phone'] ?? ''),
                    'email' => $data['email'] ?? null,
                    'address' => ($data['address'] ?? null) ?: ($inShop ? 'Bought in shop' : ''),
                    'city' => ($data['city'] ?? null) ?: 'Dhaka',
                    'notes' => $data['notes'] ?? null,
                    'delivery_method' => $data['delivery_method'],
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_status'],
                    'paid_at' => $data['payment_status'] === 'paid' ? now() : null,
                    'status' => $data['status'],
                    'source' => $data['source'],
                    'subtotal' => $subtotal,
                    'delivery_charge' => $delivery,
                    'discount' => $discount,
                    'total' => max(0, $subtotal - $discount + $delivery),
                ];
            },
            [
                'error_key' => 'items',
                'history' => 'Order created by '.$admin->name.' ('.Order::SOURCES[$data['source']].').',
                'notify' => $request->boolean('notify') && filled($data['phone'] ?? null),
            ],
        );

        return redirect()->route('admin.orders.show', $order)->with('success', "Order #{$order->order_number} created.");
    }
}
