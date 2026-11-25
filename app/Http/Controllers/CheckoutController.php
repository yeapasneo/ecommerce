<?php

namespace App\Http\Controllers;

use App\Messaging\PhoneVerification;
use App\Messaging\Sms\SmsException;
use App\Models\BlockedPhone;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Setting;
use App\Payments\PaymentManager;
use App\Services\AbandonedCarts;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Analytics;
use App\Support\Phone;
use Illuminate\Http\Request;
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
        if ($user && Setting::enabled('abandoned_cart_enabled')) {
            app(AbandonedCarts::class)->track([]);
        }
        analytics()->push('begin_checkout', [
            'currency' => 'BDT',
            'value' => (float) ($summary['subtotal'] - $summary['discount']),
            'items' => collect($summary['items'])->map(fn ($i) => ['item_id' => $i['product']['sku'] ?? null, 'item_name' => $i['product']['name'] ?? null, 'price' => (float) ($i['product']['price'] ?? 0), 'quantity' => $i['quantity']])->all(),
        ]);

        return Inertia::render('Checkout', [
            'cart' => $summary,
            'paymentMethods' => $this->payments->checkoutMethods(),
            'otp' => [
                'required' => app(PhoneVerification::class)->required(),
                'verified' => session('verified_phones', []),
            ],
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

        $this->guardPhone($data);

        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $coupon = null;
        $order = app(OrderService::class)->place(
            $items->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->variant_id, 'quantity' => $i->quantity])->all(),
            function (float $subtotal) use ($data, &$coupon) {
                $coupon = $this->cart->appliedCoupon($subtotal);
                $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
                $delivery = CartService::deliveryCharge($data['delivery_method'], $data['city'], $subtotal);

                return collect($data)->except('agree')->all() + [
                    'user_id' => auth()->id(),
                    'subtotal' => $subtotal,
                    'delivery_charge' => $delivery,
                    'discount' => $discount,
                    'total' => $subtotal - $discount + $delivery,
                    'coupon_code' => $coupon?->code,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'source' => 'website',
                ];
            },
            ['after' => function () use (&$coupon) {
                if ($coupon) {
                    Coupon::whereKey($coupon->id)->increment('used');
                }
            }],
        );

        app(AbandonedCarts::class)->markRecovered($order);
        $this->cart->clear();
        session()->forget('coupon');
        session()->push('placed_orders', $order->order_number);

        if ($this->payments->isGateway($order->payment_method)) {
            return redirect()->route('payment.start', $order->order_number);
        }

        return redirect()->route('checkout.success', $order->order_number);
    }

    /** Phone checks before an order is created: OTP verification and the blocked-numbers list. */
    protected function guardPhone(array $data): void
    {
        $otp = app(PhoneVerification::class);
        if ($otp->required() && ! $otp->isVerified($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Please verify your mobile number with the code we send by SMS.']);
        }
        if (BlockedPhone::isBlocked($data['phone'])) {
            if (Setting::get('fraud_block_mode') === 'all') {
                throw ValidationException::withMessages(['phone' => 'We cannot accept orders from this number. Please call us to order.']);
            }
            if ($data['payment_method'] === 'cod') {
                throw ValidationException::withMessages(['payment_method' => 'Cash on delivery is not available for this number. Please pay online.']);
            }
        }
    }

    /** Remember who is checking out, so an abandoned cart can be followed up. */
    public function contact(Request $request, AbandonedCarts $carts)
    {
        if (! Setting::enabled('abandoned_cart_enabled')) {
            return response()->noContent();
        }
        $data = $request->validate(['name' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255']]);
        if (! empty($data['phone']) && ! Phone::isValid($data['phone'])) {
            unset($data['phone']);
        }
        $carts->track($data);

        return response()->noContent();
    }

    public function sendOtp(Request $request, PhoneVerification $otp)
    {
        abort_unless($otp->required(), 404);
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        try {
            $otp->send($data['phone']);
        } catch (SmsException $e) {
            report($e);

            return response()->json(['message' => 'We could not send the SMS right now. Please try again in a minute.'], 503);
        }

        return response()->json(['message' => 'Code sent to '.Phone::normalize($data['phone']).'.']);
    }

    public function verifyOtp(Request $request, PhoneVerification $otp)
    {
        abort_unless($otp->required(), 404);
        $data = $request->validate(['phone' => ['required', 'string', 'max:30'], 'code' => ['required', 'digits:6']]);
        $otp->verify($data['phone'], $data['code']);

        return response()->json(['message' => 'Number verified.', 'verified' => session('verified_phones', [])]);
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
            'order' => $order->only('id', 'order_number', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'subtotal', 'delivery_charge', 'discount', 'tax', 'total', 'paid_amount', 'due_amount', 'coupon_code', 'payment_method', 'payment_status', 'delivery_method', 'status', 'transaction_id', 'paid_at', 'created_at', 'items'),
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'paymentResult' => $request->hasValidSignature() ? $request->query('payment') : null,
            'payWith' => $awaitingPayment ? $this->payments->enabledGateways() : [],
        ]);
    }
}
