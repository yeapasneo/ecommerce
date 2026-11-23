<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index()
    {
        $summary = $this->cart->summary();
        if (count($summary['items']) === 0) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        $user = auth()->user();

        return Inertia::render('Checkout', [
            'cart' => $summary,
            'paymentMethods' => Order::PAYMENT_METHODS,
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
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
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

        return redirect()->route('checkout.success', $order->order_number);
    }

    public function success(string $number)
    {
        $order = Order::where('order_number', $number)->with('items')->firstOrFail();
        $allowed = (auth()->check() && $order->user_id === auth()->id()) || in_array($number, session('placed_orders', []), true);
        abort_unless($allowed, 404);

        return Inertia::render('OrderSuccess', [
            'order' => $order,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
        ]);
    }
}
