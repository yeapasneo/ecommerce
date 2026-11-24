<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class Gateway
{
    abstract public function key(): string;

    /** Settings that must be filled in before the gateway can be offered at checkout. */
    abstract protected function requiredSettings(): array;

    /** Start a payment and return the URL to send the customer to. */
    abstract public function initiate(Order $order): string;

    /** Confirm the payment with the gateway after the customer comes back. */
    abstract public function verify(Order $order, Request $request, string $result): PaymentResult;

    public function label(): string
    {
        return Order::PAYMENT_METHODS[$this->key()];
    }

    public function isConfigured(): bool
    {
        foreach ($this->requiredSettings() as $key) {
            if (blank(Setting::get($key))) {
                return false;
            }
        }

        return true;
    }

    public function isEnabled(): bool
    {
        return Setting::enabled($this->key().'_enabled') && $this->isConfigured();
    }

    protected function sandbox(): bool
    {
        return Setting::enabled($this->key().'_sandbox');
    }

    protected function setting(string $name): ?string
    {
        return Setting::get($this->key().'_'.$name);
    }

    /** Where the gateway sends the customer back to. The order number is ours, not the gateway's. */
    protected function callbackUrl(Order $order, string $result = 'return'): string
    {
        return route('payment.callback', ['gateway' => $this->key(), 'result' => $result, 'order' => $order->order_number]);
    }

    protected function amount(Order $order): string
    {
        return number_format((float) $order->total, 2, '.', '');
    }

    protected function amountCovers(Order $order, $paid): bool
    {
        return is_numeric($paid) && round((float) $paid, 2) >= round((float) $order->total, 2);
    }

    /** A per-attempt id, so a retried payment doesn't clash with the first try. */
    protected function attemptId(Order $order): string
    {
        return $order->order_number.now()->format('His');
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->acceptJson();
    }

    protected function productSummary(Order $order): string
    {
        return Str::limit($order->items->pluck('name')->implode(', ') ?: 'Order '.$order->order_number, 250, '');
    }

    protected function fail(string $message, array $context = []): never
    {
        Log::warning("[{$this->key()}] {$message}", $context);

        throw new PaymentException($message);
    }
}
