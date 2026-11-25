<?php

namespace App\Services;

use App\Jobs\SendProductAlerts;
use App\Messaging\MailConfig;
use App\Messaging\Sms\SmsManager;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Back-in-stock alerts ("Notify me") and price-drop alerts for wishlisted products,
 * sent by SMS and/or email. Triggered from Product/ProductVariant "updated" events.
 */
class ProductAlerts
{
    /** Price-drop alerts for the same product are sent at most once a week per person. */
    public const PRICE_DROP_COOLDOWN_DAYS = 7;

    public function __construct(protected SmsManager $sms) {}

    /** Model hook: queue alerts when something comes back in stock or gets cheaper. */
    public static function watch(Model $model): void
    {
        $product = $model instanceof ProductVariant ? $model->product()->first() : $model;
        if (! $product instanceof Product || $product->is_active === false) { // null = not loaded yet (defaults to on sale)
            return;
        }
        $variantId = $model instanceof ProductVariant ? $model->id : null;

        if (self::cameBack($model) && Setting::enabled('back_in_stock_enabled')) {
            SendProductAlerts::dispatch('back_in_stock', $product->id, $variantId)->afterCommit();
        }
        if ($model instanceof Product && $model->wasChanged('price') && Setting::enabled('price_drop_alerts')) {
            $old = (float) $model->getRawOriginal('price'); // before the save (not yet synced in "updated")
            $new = (float) $model->getAttributes()['price'];
            if ($new > 0 && $old > 0 && $new <= $old * 0.98) { // ignore tiny changes
                SendProductAlerts::dispatch('price_drop', $product->id, null, $old)->afterCommit();
            }
        }
    }

    protected static function cameBack(Model $model): bool
    {
        if ($model instanceof ProductVariant) {
            return $model->wasChanged('stock') && (int) $model->getOriginal('stock') <= 0 && $model->stock > 0 && $model->is_active;
        }
        if ($model->has_variants) {
            return false; // handled per variant
        }
        $wasOut = (int) $model->getOriginal('stock') <= 0 || $model->getOriginal('stock_status') === 'out_of_stock';
        $isIn = $model->stock > 0 && $model->stock_status === 'in_stock';

        return ($model->wasChanged('stock') || $model->wasChanged('stock_status')) && $wasOut && $isIn;
    }

    /** Save a "Notify me" request. */
    public function subscribe(Product $product, ?ProductVariant $variant, ?string $phone, ?string $email, ?User $user): StockAlert
    {
        $phone = $phone ? Phone::normalize($phone) : null;

        return StockAlert::updateOrCreate(
            ['product_id' => $product->id, 'variant_id' => $variant?->id, 'type' => 'back_in_stock', 'notified_at' => null]
                + ($phone ? ['phone' => $phone] : ['email' => $email]),
            ['user_id' => $user?->id, 'phone' => $phone, 'email' => $email],
        );
    }

    /** Send waiting back-in-stock alerts. Returns how many people were told. */
    public function sendBackInStock(Product $product, ?int $variantId): int
    {
        $variant = $variantId ? ProductVariant::find($variantId) : null;
        $alerts = StockAlert::where('product_id', $product->id)->where('type', 'back_in_stock')->whereNull('notified_at')
            ->when($variantId, fn ($q) => $q->where(fn ($q) => $q->where('variant_id', $variantId)->orWhereNull('variant_id')), fn ($q) => $q->whereNull('variant_id'))
            ->get();
        $name = $product->name.($variant ? " ({$variant->name})" : '');
        $sent = 0;
        foreach ($alerts as $alert) {
            $text = $this->render(Setting::get('sms_template_back_in_stock'), $product, ['product' => $name]);
            if ($this->deliver($alert->phone, $alert->email, "{$name} is back in stock", $text, 'back_in_stock')) {
                $sent++;
            }
            $alert->update(['notified_at' => now()]);
        }

        return $sent;
    }

    /** Tell people who wishlisted the product (and asked for alerts) that it is cheaper. */
    public function sendPriceDrop(Product $product, float $oldPrice): int
    {
        $price = (float) $product->getRawOriginal('price');
        $recent = StockAlert::where('product_id', $product->id)->where('type', 'price_drop')
            ->where('notified_at', '>=', now()->subDays(self::PRICE_DROP_COOLDOWN_DAYS))->pluck('user_id')->filter()->all();
        $users = User::whereIn('id', DB::table('wishlists')->where('product_id', $product->id)->select('user_id'))
            ->whereNotIn('id', $recent)->where('is_active', true)->get(['id', 'name', 'phone', 'email']);
        $sent = 0;
        foreach ($users as $user) {
            $text = $this->render(Setting::get('sms_template_price_drop'), $product, [
                'product' => $product->name, 'name' => strtok((string) $user->name, ' ') ?: 'there',
                'price' => number_format($price), 'old' => number_format($oldPrice),
            ]);
            if ($this->deliver($user->phone, $user->email, "Price drop: {$product->name}", $text, 'price_drop')) {
                $sent++;
            }
            StockAlert::create(['product_id' => $product->id, 'user_id' => $user->id, 'type' => 'price_drop', 'phone' => $user->phone, 'email' => $user->email, 'price' => $price, 'notified_at' => now()]);
        }

        return $sent;
    }

    protected function render(?string $template, Product $product, array $values): string
    {
        $values += ['site' => Setting::get('site_name'), 'link' => url("/product/{$product->slug}")];

        return trim(preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($values[$m[1]] ?? $m[0]), (string) $template));
    }

    /** SMS when there's a valid phone and SMS is set up; email otherwise (or as well). */
    protected function deliver(?string $phone, ?string $email, string $subject, string $text, string $event): bool
    {
        $ok = false;
        if ($phone && Phone::isValid($phone) && $this->sms->isConfigured()) {
            $ok = $this->attempt('sms', $phone, $text, $event, fn () => $this->sms->send($phone, $text)) || $ok;
        }
        if ($email) {
            $ok = $this->attempt('email', $email, $text, $event, function () use ($email, $subject, $text) {
                MailConfig::configure();
                Mail::raw($text, fn ($m) => $m->to($email)->subject($subject));
            }) || $ok;
        }

        return $ok;
    }

    protected function attempt(string $channel, string $to, string $text, string $event, callable $send): bool
    {
        try {
            $send();
            NotificationLog::create(['channel' => $channel, 'event' => $event, 'recipient' => $to, 'message' => $text, 'status' => 'sent']);

            return true;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => $channel, 'event' => $event, 'recipient' => $to, 'message' => $text, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }
}
