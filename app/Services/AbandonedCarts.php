<?php

namespace App\Services;

use App\Messaging\MailConfig;
use App\Messaging\Sms\SmsManager;
use App\Models\AbandonedCheckout;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Checkouts started but not finished, and the reminders that bring shoppers back. */
class AbandonedCarts
{
    public function __construct(protected CartService $cart, protected SmsManager $sms) {}

    /** Save the shopper's contact details and current cart (called from the checkout page). */
    public function track(array $contact): ?AbandonedCheckout
    {
        $summary = $this->cart->summary();
        if (count($summary['items']) === 0) {
            return null;
        }
        $user = auth()->user();
        $record = AbandonedCheckout::where('session_id', session()->getId())->whereNull('recovered_at')->first()
            ?? new AbandonedCheckout(['session_id' => session()->getId(), 'token' => Str::random(40)]);

        $record->fill([
            'user_id' => $user?->id,
            'name' => ($contact['name'] ?? null) ?: $record->name ?: $user?->name,
            'phone' => ($contact['phone'] ?? null) ?: $record->phone ?: $user?->phone,
            'email' => ($contact['email'] ?? null) ?: $record->email ?: $user?->email,
            'items' => collect($summary['items'])->map(fn ($i) => [
                'product_id' => $i['product']['id'], 'variant_id' => $i['variant']['id'] ?? null, 'quantity' => $i['quantity'],
                'name' => $i['product']['name'].($i['variant'] ? " ({$i['variant']['name']})" : ''), 'price' => $i['product']['price'],
                'image_url' => $i['product']['image_url'], 'slug' => $i['product']['slug'],
            ])->values()->all(),
            'total' => $summary['subtotal'] - $summary['discount'],
        ]);
        if (! $record->phone && ! $record->email) {
            return null; // nothing to remind them with
        }
        $record->touch();
        $record->save();

        return $record;
    }

    public function markRecovered(Order $order): void
    {
        $key = Phone::key($order->phone);
        AbandonedCheckout::whereNull('recovered_at')->where('created_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->where('session_id', session()->getId())
                ->when($key !== '', fn ($q) => $q->orWhereRaw("right(regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'), 10) = ?", [$key])))
            ->update(['recovered_at' => now(), 'order_id' => $order->id]);
    }

    /** Carts waiting long enough for their one reminder. */
    public function due()
    {
        $delay = max(1, (int) Setting::get('abandoned_cart_delay_hours', 2));

        return AbandonedCheckout::whereNull('reminded_at')->whereNull('recovered_at')
            ->where('updated_at', '<=', now()->subHours($delay))
            ->where('updated_at', '>=', now()->subDays(3))
            ->get();
    }

    /** Send the reminder by SMS and/or email. Returns the channels used. */
    public function remind(AbandonedCheckout $record): array
    {
        $link = route('cart.recover', $record->token);
        $site = Setting::get('site_name');
        $sent = [];

        if ($record->phone && Phone::isValid($record->phone) && $this->sms->isConfigured()) {
            $message = strtr((string) Setting::get('sms_template_abandoned_cart'), [
                '{name}' => Str::of((string) $record->name)->before(' ')->value() ?: 'there',
                '{total}' => number_format($record->total), '{link}' => $link, '{site}' => $site,
            ]);
            $sent[] = $this->attempt($record, 'sms', $record->phone, $message, fn () => $this->sms->send($record->phone, $message));
        }
        if ($record->email) {
            $lines = collect($record->items)->map(fn ($i) => "• {$i['name']} × {$i['quantity']}")->implode("\n");
            $body = "Hi {$record->name},\n\nYou left these items in your cart at {$site}:\n\n{$lines}\n\nTotal: Tk ".number_format($record->total)."\n\nComplete your order here: {$link}\n\nThanks,\n{$site}";
            $sent[] = $this->attempt($record, 'email', $record->email, 'Items waiting in your cart', function () use ($record, $body, $site) {
                MailConfig::configure();
                Mail::raw($body, fn ($m) => $m->to($record->email)->subject("You left something in your cart at {$site}"));
            });
        }
        $record->forceFill(['reminded_at' => now()])->saveQuietly();

        return array_values(array_filter($sent));
    }

    protected function attempt(AbandonedCheckout $record, string $channel, string $to, string $message, callable $send): ?string
    {
        try {
            $send();
            NotificationLog::create(['channel' => $channel, 'event' => 'abandoned_cart', 'recipient' => $to, 'message' => $message, 'status' => 'sent']);

            return $channel;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => $channel, 'event' => 'abandoned_cart', 'recipient' => $to, 'message' => $message, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return null;
        }
    }

    /** Put a saved cart back into this browser's cart. */
    public function restore(AbandonedCheckout $record): int
    {
        $added = 0;
        $inCart = $this->cart->items()->map(fn ($i) => $i->product_id.'-'.$i->variant_id)->all();
        foreach ($record->items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product || ! $product->is_active || in_array($item['product_id'].'-'.($item['variant_id'] ?? ''), $inCart, true)) {
                continue;
            }
            $variant = ! empty($item['variant_id']) ? $product->variants()->find($item['variant_id']) : null;
            $variant?->setRelation('product', $product);
            if ($variant ? $variant->isPurchasable() : (! $product->has_variants && $product->isPurchasable())) {
                $this->cart->add($product->id, (int) $item['quantity'], $variant?->id);
                $added++;
            }
        }

        return $added;
    }
}
