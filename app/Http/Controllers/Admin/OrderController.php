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
            'editable' => self::editable($order),
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
            'paid_amount' => ['nullable', 'required_if:payment_status,partial', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['paid_amount.required_if' => 'Enter how much has been paid.']);
        if ($order->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()->with('error', 'Cancelled orders cannot be re-opened.');
        }
        $paid = match ($data['payment_status']) {
            'paid' => (float) $order->total,
            'partial' => min((float) $data['paid_amount'], (float) $order->total),
            'unpaid' => 0.0,
            default => (float) $order->paid_amount,
        };
        if ($data['payment_status'] === 'partial' && $paid >= (float) $order->total) {
            $data['payment_status'] = 'paid';
        }
        $nowPaid = $order->payment_status !== 'paid' && $data['payment_status'] === 'paid';
        $order->update(['payment_status' => $data['payment_status'], 'paid_amount' => $paid] + ($nowPaid ? ['paid_at' => now()] : []));
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
        return Inertia::render('Admin/Orders/Create', $this->formOptions() + ['order' => null]);
    }

    /** Orders taken by staff (phone, Facebook, in shop). */
    public function store(Request $request, OrderService $service)
    {
        $data = $this->validatedStaffOrder($request);
        $order = $service->place(
            $this->lines($data),
            fn (float $subtotal) => $this->attributes($data, $subtotal),
            [
                'error_key' => 'items',
                'history' => 'Order created by '.$request->user()->name.' ('.Order::SOURCES[$data['source']].').',
                'notify' => $request->boolean('notify') && filled($data['phone'] ?? null),
            ],
        );

        return redirect()->route('admin.orders.show', $order)->with('success', "Order #{$order->order_number} created.");
    }

    /** Items and details can change until the order is handed to a courier. */
    public static function editable(Order $order): bool
    {
        return in_array($order->status, ['pending', 'processing'], true) && ! $order->courier_consignment_id && $order->payment_status !== 'refunded';
    }

    public function edit(Order $order)
    {
        if (! self::editable($order)) {
            return redirect()->route('admin.orders.show', $order)->with('error', 'Only pending or processing orders that are not with a courier can be edited.');
        }
        $order->load('items.product:id,name,sku,image,stock,stock_status,has_variants', 'items.variant');

        return Inertia::render('Admin/Orders/Create', $this->formOptions() + [
            'order' => [
                ...$order->only('id', 'order_number', 'source', 'status', 'user_id', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'notes',
                    'delivery_method', 'delivery_charge', 'discount', 'tax', 'payment_method', 'payment_status', 'paid_amount', 'subtotal'),
                'items' => $order->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'variant_id' => $i->variant_id,
                    'quantity' => $i->quantity,
                    'price' => (float) $i->price,
                    'meta' => [
                        'name' => $i->name,
                        'variant_name' => $i->variant_name,
                        'sku' => $i->sku,
                        'image_url' => $i->variant?->image_url ?: $i->product?->image_url,
                        // stock shown in the editor includes what this order already holds
                        'stock' => ($i->variant ? $i->variant->stock : (int) $i->product?->stock) + $i->quantity,
                        'stock_status' => $i->variant ? 'in_stock' : $i->product?->stock_status,
                    ],
                ])->values(),
            ],
        ]);
    }

    public function saveDetails(Request $request, Order $order, OrderService $service)
    {
        abort_unless(self::editable($order), 403, 'This order can no longer be edited.');
        $data = $this->validatedStaffOrder($request);
        $service->revise($order, $this->lines($data), fn (float $subtotal) => $this->attributes($data, $subtotal), 'Order edited by '.$request->user()->name.'.');

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order updated.');
    }

    protected function formOptions(): array
    {
        return [
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
            'defaultVat' => (float) Setting::get('vat_percent', 0),
            'smsReady' => app(SmsManager::class)->isConfigured(),
        ];
    }

    protected function validatedStaffOrder(Request $request): array
    {
        $inShop = $request->input('source') === 'in_shop';
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(Order::SOURCES))],
            'status' => ['required', Rule::in(['pending', 'processing', 'shipped', 'delivered'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
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
            'tax_type' => ['nullable', 'in:percent,amount'],
            'tax_value' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
            'paid_amount' => ['nullable', 'required_if:payment_status,partial', 'numeric', 'min:0'],
            'notify' => ['boolean'],
        ], [
            'items.required' => 'Add at least one product.',
            'paid_amount.required_if' => 'Enter how much the customer has paid.',
        ]);
        $keys = collect($data['items'])->map(fn ($i) => $i['product_id'].'-'.($i['variant_id'] ?? ''));
        if ($keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'A product is listed twice; change its quantity instead.']);
        }
        if (! $inShop && ! Phone::isValid($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number, e.g. 01712345678.']);
        }

        return $data;
    }

    protected function lines(array $data): array
    {
        return collect($data['items'])->map(fn ($i) => [
            'product_id' => $i['product_id'], 'variant_id' => $i['variant_id'] ?? null, 'quantity' => $i['quantity'], 'price' => $i['price'],
        ])->all();
    }

    /** Order totals: subtotal − discount + VAT (on the discounted amount) + delivery. */
    protected function attributes(array $data, float $subtotal): array
    {
        $inShop = $data['source'] === 'in_shop';
        $value = (float) ($data['discount_value'] ?? 0);
        $discount = $data['discount_type'] === 'percent' ? round($subtotal * min($value, 100) / 100, 2) : min($value, $subtotal);
        $taxable = $subtotal - $discount;
        $taxValue = (float) ($data['tax_value'] ?? 0);
        $tax = ($data['tax_type'] ?? 'percent') === 'percent' ? round($taxable * min($taxValue, 100) / 100, 2) : $taxValue;
        $delivery = $data['delivery_method'] === 'store_pickup' ? 0 : (float) $data['delivery_charge'];
        $total = max(0, round($subtotal - $discount + $tax + $delivery, 2));
        $paid = match ($data['payment_status']) {
            'paid' => $total,
            'partial' => min((float) ($data['paid_amount'] ?? 0), $total),
            default => 0,
        };
        $status = $data['payment_status'] === 'partial' && $paid >= $total ? 'paid' : $data['payment_status'];

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
            'payment_status' => $status,
            'paid_amount' => $paid,
            'paid_at' => $status === 'paid' ? now() : null,
            'status' => $data['status'],
            'source' => $data['source'],
            'subtotal' => $subtotal,
            'delivery_charge' => $delivery,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ];
    }
}
