<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\PaymentException;
use App\Payments\PaymentManager;
use App\Payments\SslCommerz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(protected PaymentManager $payments) {}

    /** Send the customer to the gateway (optionally switching to another enabled gateway). */
    public function pay(Request $request, string $number)
    {
        $order = Order::where('order_number', $number)->with('items')->firstOrFail();
        abort_unless(CheckoutController::canView($order), 404);

        if ($order->isPaid() || $order->status === 'cancelled') {
            return redirect()->route('checkout.success', $number);
        }

        $request->validate(['gateway' => ['nullable', Rule::in(array_keys($this->payments->enabledGateways()))]]);
        $method = $request->input('gateway', $order->payment_method);
        if (! $this->payments->isGateway($method) || ! $this->payments->driver($method)->isEnabled()) {
            return redirect()->route('checkout.success', $number)->with('error', 'That payment method is not available right now.');
        }
        if ($method !== $order->payment_method) {
            $order->update(['payment_method' => $method]);
        }

        try {
            $url = $this->payments->driver($method)->initiate($order);
        } catch (PaymentException $e) {
            return redirect()->route('checkout.success', $number)->with('error', 'We could not start the payment. '.$e->getMessage());
        } catch (Throwable $e) {
            Log::error("[{$method}] payment start failed", ['order' => $number, 'error' => $e->getMessage()]);

            return redirect()->route('checkout.success', $number)->with('error', 'The payment gateway is not responding. Please try again in a moment.');
        }

        // Inertia requests need a 409 + location header to leave the app.
        return Inertia::location($url);
    }

    /** The customer is sent back here by the gateway. Runs without a session (see routes/payment.php). */
    public function callback(Request $request, string $gateway, string $result, string $order)
    {
        $order = Order::where('order_number', $order)->with('items')->firstOrFail();
        abort_unless($this->payments->isGateway($gateway), 404);

        if (! $order->isPaid()) {
            try {
                $outcome = $this->payments->driver($gateway)->verify($order, $request, $result);
            } catch (Throwable $e) {
                Log::error("[{$gateway}] payment verification failed", ['order' => $order->order_number, 'error' => $e->getMessage()]);
                $outcome = null;
            }
            if ($outcome?->isPaid()) {
                $order->markPaid($gateway, $outcome->transactionId, $outcome->reference);
            } else {
                $status = $outcome?->status ?? 'failed';
                $order->histories()->create(['status' => $order->status, 'comment' => ucfirst($status).' '.$order->payment_method.' payment attempt: '.($outcome?->message ?? 'could not verify the payment.')]);

                return redirect($this->successUrl($order, $status));
            }
        }

        return redirect($this->successUrl($order, 'paid'));
    }

    /** Server-to-server payment notification (SSLCommerz IPN). */
    public function ipn(Request $request, string $gateway)
    {
        abort_unless($gateway === 'sslcommerz', 404);
        try {
            app(SslCommerz::class)->ipn($request);
        } catch (Throwable $e) {
            Log::error('[sslcommerz] IPN failed', ['error' => $e->getMessage()]);

            return response('error', 500);
        }

        return response('ok');
    }

    /** Signed, so the order page opens even though this request had no session. */
    protected function successUrl(Order $order, string $payment): string
    {
        return URL::temporarySignedRoute('checkout.success', now()->addHours(2), ['number' => $order->order_number, 'payment' => $payment]);
    }
}
