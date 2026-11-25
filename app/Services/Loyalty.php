<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points: earned when an order is delivered, spent at checkout as a discount,
 * given back if the order is cancelled and taken back on refunds. Referral bonuses are
 * paid when the invited customer's first order is delivered (so fake sign-ups earn nothing).
 */
class Loyalty
{
    public static function enabled(): bool
    {
        return Setting::enabled('loyalty_enabled');
    }

    public static function earnPer(): float
    {
        return max(1, (float) Setting::get('loyalty_earn_per', 100));
    }

    public static function pointValue(): float
    {
        return max(0.01, (float) Setting::get('loyalty_point_value', 1));
    }

    /** Points an order earns: 1 per "earn per" Taka actually paid for the items. */
    public static function pointsFor(Order $order): int
    {
        $spent = (float) $order->subtotal - (float) $order->discount - (float) $order->points_discount;

        return max(0, (int) floor($spent / self::earnPer()));
    }

    /** Most points a customer can use on a cart of this value (0 if below the minimum). */
    public static function redeemable(?User $user, float $itemsValue): int
    {
        if (! $user || ! self::enabled()) {
            return 0;
        }
        $cap = (int) floor($itemsValue * max(0, min(100, (float) Setting::get('loyalty_max_percent', 20))) / 100 / self::pointValue());
        $points = min((int) $user->points, $cap);

        return $points >= (int) Setting::get('loyalty_min_redeem', 100) ? $points : 0;
    }

    public function add(User $user, int $points, string $type, ?Order $order = null, ?string $note = null, ?int $by = null): ?PointTransaction
    {
        if ($points === 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $points, $type, $order, $note, $by) {
            $user = User::lockForUpdate()->find($user->id);
            $before = (int) $user->points;
            $balance = max(0, $before + $points); // never below zero
            $user->forceFill(['points' => $balance])->saveQuietly();

            return PointTransaction::create(['user_id' => $user->id, 'order_id' => $order?->id, 'points' => $balance - $before,
                'balance_after' => $balance, 'type' => $type, 'note' => $note, 'created_by' => $by]);
        });
    }

    /** Checkout: take the points the order used. */
    public function redeem(Order $order): void
    {
        if ($order->user_id && $order->points_redeemed > 0) {
            $this->add($order->user, -$order->points_redeemed, 'redeem', $order, "Order #{$order->order_number}");
        }
    }

    /** Order delivered: give points (once), plus referral bonuses on a first delivered order. */
    public function delivered(Order $order): void
    {
        if (! self::enabled() || ! $order->user_id || $order->points_earned > 0) {
            return;
        }
        $user = $order->user;
        $points = self::pointsFor($order);
        if ($points > 0) {
            $order->forceFill(['points_earned' => $points])->saveQuietly();
            $this->add($user, $points, 'earn', $order, "Order #{$order->order_number}");
        }

        if (Setting::enabled('referral_enabled') && $user->referred_by
            && ! PointTransaction::where('user_id', $user->id)->where('type', 'welcome')->exists()
            && ! Order::where('user_id', $user->id)->where('status', 'delivered')->whereKeyNot($order->id)->exists()) {
            $this->add($user, (int) Setting::get('referral_welcome', 100), 'welcome', $order, 'Welcome bonus for joining with an invite');
            if ($referrer = User::find($user->referred_by)) {
                $this->add($referrer, (int) Setting::get('referral_bonus', 200), 'referral', $order, "{$user->name} placed their first order");
            }
        }
    }

    /** Order cancelled: give back the points it used (and take back any it earned). */
    public function cancelled(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }
        if ($order->points_redeemed > 0 && ! PointTransaction::where('order_id', $order->id)->where('type', 'refund')->exists()) {
            $this->add($order->user, $order->points_redeemed, 'refund', $order, "Order #{$order->order_number} cancelled");
        }
        if ($order->points_earned > 0) {
            $this->add($order->user, -$order->points_earned, 'reverse', $order, "Order #{$order->order_number} cancelled");
            $order->forceFill(['points_earned' => 0])->saveQuietly();
        }
    }

    /** Money refunded: take back the points that money earned. */
    public function refunded(Order $order, float $amount): void
    {
        if (! $order->user_id || $order->points_earned <= 0) {
            return;
        }
        $back = min((int) $order->points_earned, (int) floor($amount / self::earnPer()));
        if ($back > 0) {
            $this->add($order->user, -$back, 'reverse', $order, 'Refund on order #'.$order->order_number);
            $order->forceFill(['points_earned' => $order->points_earned - $back])->saveQuietly();
        }
    }
}
