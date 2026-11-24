<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Payments\PaymentManager;
use App\Services\CartService;
use App\Support\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart, protected PaymentManager $payments) {}

    /** Orders are visible to their owner and to the browser session that placed them. */
    public static function canView(Order $order): bool
    {
        return (auth()->check() && $order->user_id === auth()->id())
            || in_array($order->order_number, session('placed_orders', []), true);
    }

    public function index()
    {
        $summary = $this->cart->summary();
        if (count($summary['items']) === 0) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        $user = auth()->user();
        seo()->title('Checkout');
        analytics()->push('begin_checkout', [
            'currency' => 'BDT',
            'value' => (float) ($summary['subtotal'] - $summary['discount']),
            'items' => collect($summary['items'])->map(fn ($i) => ['item_id' => $i['product']['sku'] ?? null, 'item_name' => $i['product']['name'] ?? null, 'price' => (float) ($i['product']['price'] ?? 0), 'quantity' => $i['quantity']])->all(),
        ]);

        return Inertia::render('Checkout', [
            'cart' => $summary,
            'paymentMethods' => $this->payments->checkoutMethods(),
            'initialDelivery' => CartService::deliveryCharge('home_delivery', $user?->city ?? 'Dhaka', $summary['subtotal']),
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'defaults' => [
                'first_name' => $user ? explode(' ', $user->name, 2)[0] : '',
                'last_name' => $user ? (explode(' ', $user->name, 2)[1] ?? '') : '',
                'email' => $user?->email ?? '',
                'phone' => $user?->phone ?? '',
                'address' => $user?->address ?? '',
                'city' => $user?->city ?? 'Dhaka',
            ],
        ]);
    }

    /** Delivery charge preview used by the checkout page when inputs change. */
    public function quote(Request $request)
    {
        $subtotal = $this->cart->subtotal();

        return response()->json([
            'delivery_charge' => CartService::deliveryCharge(
                (string) $request->input('delivery_method', 'home_delivery'),
                (string) $request->input('city', ''),
                $subtotal
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_method' => ['required', Rule::in(array_keys(Order::DELIVERY_METHODS))],
            'payment_method' => ['required', Rule::in(array_keys($this->payments->checkoutMethods()))],
            'agree' => ['accepted'],
        ], ['agree.accepted' => 'You must agree to the terms and conditions.']);

        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $order = DB::transaction(function () use ($data, $items) {
            $subtotal = 0;
            $lines = [];
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if (! $product || ! $product->isPurchasable()) {
                    throw ValidationException::withMessages(['cart' => "{$item->product->name} is no longer available. Please remove it from your cart."]);
                }
                if ($product->stock_status === 'in_stock' && $product->stock < $item->quantity) {
                    throw ValidationException::withMessages(['cart' => "Only {$product->stock} unit(s) of {$product->name} are in stock."]);
                }
                $subtotal += $product->price * $item->quantity;
                $lines[] = [$product, $item->quantity];
            }

            $coupon = $this->cart->appliedCoupon($subtotal);
            $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
            $delivery = CartService::deliveryCharge($data['delivery_method'], $data['city'], $subtotal);

            $order = Order::create(collect($data)->except('agree')->all() + [
                'order_number' => Order::generateNumber(),
                'user_id' => auth()->id(),
                'subtotal' => $subtotal,
                'delivery_charge' => $delivery,
                'discount' => $discount,
                'total' => $subtotal - $discount + $delivery,
                'coupon_code' => $coupon?->code,
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            foreach ($lines as [$product, $qty]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $qty,
                    'total' => $product->price * $qty,
                ]);
                if ($product->stock_status === 'in_stock') {
                    $product->decrement('stock', $qty);
                    if ($product->stock <= 0) {
                        $product->update(['stock_status' => 'out_of_stock']);
                    }
                }
            }
            if ($coupon) {
                Coupon::whereKey($coupon->id)->increment('used');
            }
            $order->histories()->create(['status' => 'pending', 'comment' => 'Order placed.']);

            return $order;
        });

        $this->cart->clear();
        session()->forget('coupon');
        session()->push('placed_orders', $order->order_number);

        if ($this->payments->isGateway($order->payment_method)) {
            return redirect()->route('payment.start', $order->order_number);
        }

        return redirect()->route('checkout.success', $order->order_number);
    }

    public function success(Request $request, string $number)
    {
        $order = Order::where('order_number', $number)->with('items')->firstOrFail();
        // Gateways send customers back with a signed link (their session cookie may not survive the trip).
        if ($request->hasValidSignature() && ! in_array($number, session('placed_orders', []), true)) {
            session()->push('placed_orders', $number);
        }
        abort_unless(self::canView($order), 404);

        // Report the purchase to analytics once per order.
        if (! in_array($number, session('tracked_orders', []), true)) {
            session()->push('tracked_orders', $number);
            analytics()->push('purchase', Analytics::orderParams($order));
        }
        seo()->title('Order '.$order->order_number);

        $awaitingPayment = $this->payments->isGateway($order->payment_method) && ! $order->isPaid() && $order->status !== 'cancelled';

        return Inertia::render('OrderSuccess', [
            'order' => $order->only('id', 'order_number', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'subtotal', 'delivery_charge', 'discount', 'total', 'coupon_code', 'payment_method', 'payment_status', 'delivery_method', 'status', 'transaction_id', 'paid_at', 'created_at', 'items'),
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'paymentResult' => $request->hasValidSignature() ? $request->query('payment') : null,
            'payWith' => $awaitingPayment ? $this->payments->enabledGateways() : [],
        ]);
    }
}
