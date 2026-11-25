<?php

namespace App\Support;

use App\Models\Setting;

/** Card EMI options shown on product pages. Plans are "months:interest%" pairs, e.g. "3:0, 6:0, 12:7". */
class Emi
{
    public static function plans(float $price): ?array
    {
        if (! Setting::enabled('emi_enabled') || $price <= 0 || $price < (float) Setting::get('emi_min_amount', 5000)) {
            return null;
        }
        $plans = collect(explode(',', (string) Setting::get('emi_plans')))
            ->map(fn ($p) => array_map('trim', explode(':', $p)))
            ->filter(fn ($p) => ctype_digit($p[0] ?? '') && (int) $p[0] > 0)
            ->map(function ($p) use ($price) {
                $months = (int) $p[0];
                $rate = (float) ($p[1] ?? 0);
                $total = round($price * (1 + $rate / 100));

                return ['months' => $months, 'rate' => $rate, 'total' => $total, 'monthly' => ceil($total / $months)];
            })
            ->sortBy('months')->values()->all();

        return $plans ? ['plans' => $plans, 'note' => Setting::get('emi_note')] : null;
    }
}
