<?php

namespace App\Services;

use App\Models\BlockedPhone;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Customer validity": how often a phone number's past orders were delivered
 * versus cancelled or returned, in this shop and (optionally) across couriers.
 */
class FraudCheck
{
    /** Orders placed with this number in this shop. */
    public function internal(string $phone, ?int $exceptOrderId = null): array
    {
        $rows = Order::query()
            ->whereRaw("right(regexp_replace(phone, '[^0-9]', '', 'g'), 10) = ?", [Phone::key($phone)])
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $delivered = (int) ($rows['delivered'] ?? 0);
        $cancelled = (int) ($rows['cancelled'] ?? 0);

        return [
            'total' => (int) $rows->sum(),
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'ongoing' => (int) $rows->sum() - $delivered - $cancelled,
            'ratio' => $delivered + $cancelled > 0 ? (int) round($delivered / ($delivered + $cancelled) * 100) : null,
        ];
    }

    public function hasExternal(): bool
    {
        return Setting::get('fraud_provider') === 'bdcourier' && filled(Setting::get('bdcourier_api_key'));
    }

    /**
     * Delivery record across Bangladeshi couriers via bdcourier.com (cached for 6 hours).
     *
     * @throws RuntimeException
     */
    public function external(string $phone): array
    {
        $number = Phone::normalize($phone);

        return Cache::remember("fraud:bdcourier:{$number}", 6 * 3600, function () use ($number) {
            $response = Http::timeout(20)->acceptJson()
                ->withToken((string) Setting::get('bdcourier_api_key'))
                ->post('https://bdcourier.com/api/courier-check', ['phone' => $number]);
            if (! $response->successful() || $response->json('status') !== 'success') {
                throw new RuntimeException('BD Courier lookup failed: '.($response->json('message') ?? $response->json('error') ?? 'HTTP '.$response->status()));
            }

            $data = $response->json('courier_data') ?? [];
            $couriers = collect($data)->except('summary')
                ->map(fn ($c, $key) => [
                    'name' => $c['name'] ?? Str::headline($key),
                    'total' => (int) ($c['total_parcel'] ?? 0),
                    'delivered' => (int) ($c['success_parcel'] ?? 0),
                    'cancelled' => (int) ($c['cancelled_parcel'] ?? 0),
                ])
                ->filter(fn ($c) => $c['total'] > 0)
                ->values()->all();
            $summary = $data['summary'] ?? [];

            return [
                'total' => (int) ($summary['total_parcel'] ?? array_sum(array_column($couriers, 'total'))),
                'delivered' => (int) ($summary['success_parcel'] ?? array_sum(array_column($couriers, 'delivered'))),
                'cancelled' => (int) ($summary['cancelled_parcel'] ?? array_sum(array_column($couriers, 'cancelled'))),
                'ratio' => isset($summary['success_ratio']) ? (int) round((float) $summary['success_ratio']) : null,
                'couriers' => $couriers,
                'checked_at' => now()->toIso8601String(),
            ];
        });
    }

    /** low | medium | high | new | blocked */
    public static function risk(bool $blocked, array ...$records): string
    {
        if ($blocked) {
            return 'blocked';
        }
        $delivered = array_sum(array_column($records, 'delivered'));
        $cancelled = array_sum(array_column($records, 'cancelled'));
        if ($delivered + $cancelled === 0) {
            return 'new';
        }
        $ratio = $delivered / ($delivered + $cancelled) * 100;

        return $ratio >= 80 ? 'low' : ($ratio >= 50 ? 'medium' : 'high');
    }

    /** Everything the admin order page shows; the courier lookup only when asked for. */
    public function report(string $phone, ?int $exceptOrderId = null, bool $withExternal = false): array
    {
        $internal = $this->internal($phone, $exceptOrderId);
        $blocked = BlockedPhone::where('phone', Phone::normalize($phone))->first();
        $external = null;
        $error = null;
        if ($withExternal && $this->hasExternal()) {
            try {
                $external = $this->external($phone);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'phone' => Phone::normalize($phone),
            'internal' => $internal,
            'external' => $external,
            'externalError' => $error,
            'externalAvailable' => $this->hasExternal(),
            'blocked' => $blocked ? ['reason' => $blocked->reason, 'at' => $blocked->created_at] : null,
            'risk' => self::risk((bool) $blocked, $internal, $external ?? []),
        ];
    }
}
