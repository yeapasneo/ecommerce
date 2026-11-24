<?php

namespace App\Couriers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class Courier
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract protected function requiredSettings(): array;

    /** Book the parcel with the courier and store the consignment on the order. */
    abstract public function send(Order $order, array $input): void;

    /** Ask the courier for the current status; returns the raw status. */
    abstract public function fetchStatus(Order $order): ?string;

    abstract public function trackingUrl(Order $order): ?string;

    /** Handle a status push from the courier. */
    abstract public function webhook(Request $request);

    /** Which order status a courier status means, if any. */
    protected function orderStatusFor(string $status): ?string
    {
        return match ($status) {
            'delivered', 'partial_delivered', 'partial_delivery' => 'delivered',
            default => null,
        };
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

    protected function setting(string $name): ?string
    {
        return Setting::get($this->key().'_'.$name);
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->acceptJson()->asJson();
    }

    public function refresh(Order $order): ?string
    {
        $status = $this->fetchStatus($order);
        if ($status) {
            $this->applyStatus($order, $status);
        }

        return $status;
    }

    /** Record a courier status, log it in the order history and move the order forward when it's delivered. */
    public function applyStatus(Order $order, string $raw, ?float $fee = null): void
    {
        $status = Str::of($raw)->lower()->replace(['-', ' ', '.'], '_')->toString();
        $changed = $status !== $order->courier_status;
        $order->update(array_filter([
            'courier_status' => $status,
            'courier_status_at' => now(),
            'courier_fee' => $fee,
        ], fn ($v) => $v !== null));

        if (! $changed) {
            return;
        }
        $comment = $this->label().': '.self::statusLabel($status).'.';
        $mapped = $this->orderStatusFor($status);
        if ($mapped && Setting::enabled('courier_sync_status') && ! in_array($order->status, [$mapped, 'cancelled'], true)) {
            app(OrderService::class)->changeStatus($order, $mapped, $comment);
        } else {
            $order->histories()->create(['status' => $order->status, 'comment' => $comment]);
        }
    }

    /** After booking: mark the order shipped and note the consignment. */
    protected function booked(Order $order, array $attributes): void
    {
        $order->update($attributes + ['courier' => $this->key(), 'courier_sent_at' => now(), 'courier_status_at' => now()]);
        $comment = "Handed to {$this->label()} (consignment {$order->courier_consignment_id}).";
        if (in_array($order->status, ['pending', 'processing'], true)) {
            app(OrderService::class)->changeStatus($order, 'shipped', $comment);
        } else {
            $order->histories()->create(['status' => $order->status, 'comment' => $comment]);
        }
    }

    public static function statusLabel(?string $status): string
    {
        return $status ? Str::headline($status) : 'Unknown';
    }

    /** Bangladeshi mobile number as 01XXXXXXXXX. */
    public static function phone(string $phone): string
    {
        return Phone::normalize($phone);
    }

    protected function fail(string $message, ?Response $response = null): never
    {
        Log::warning("[{$this->key()}] {$message}", ['response' => $response?->json() ?? $response?->body()]);

        throw new CourierException($message);
    }

    /** Turn {"errors": {"field": ["msg"]}} or {"message": "..."} into one readable line. */
    protected function errorText(Response $response, string $fallback): string
    {
        $errors = collect($response->json('errors') ?? [])->flatten()->filter()->implode(' ');

        return trim($errors ?: ($response->json('message') ?: $fallback));
    }
}
