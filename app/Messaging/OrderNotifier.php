<?php

namespace App\Messaging;

use App\Jobs\SendOrderNotification;
use App\Messaging\Sms\SmsManager;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Phone;

/** Decides which SMS / emails an order event triggers and queues them. */
class OrderNotifier
{
    public const EVENTS = [
        'order_placed' => 'Order placed',
        'order_confirmed' => 'Order confirmed (processing)',
        'order_shipped' => 'Order shipped',
        'order_delivered' => 'Order delivered',
        'order_cancelled' => 'Order cancelled',
        'payment_received' => 'Payment received',
    ];

    /** Order status → event. */
    public const STATUS_EVENTS = [
        'processing' => 'order_confirmed',
        'shipped' => 'order_shipped',
        'delivered' => 'order_delivered',
        'cancelled' => 'order_cancelled',
    ];

    public function __construct(protected SmsManager $sms) {}

    public function notify(Order $order, string $event): void
    {
        if (! isset(self::EVENTS[$event])) {
            return;
        }
        if (Setting::enabled("notify_sms_{$event}") && $this->sms->isConfigured() && Phone::isValid($order->phone)) {
            SendOrderNotification::dispatch($order->id, $event, 'sms')->afterCommit();
        }
        if (Setting::enabled("notify_email_{$event}") && filled($order->email)) {
            SendOrderNotification::dispatch($order->id, $event, 'email')->afterCommit();
        }
        if ($event === 'order_placed' && filled(Setting::get('notify_admin_email'))) {
            SendOrderNotification::dispatch($order->id, $event, 'admin_email')->afterCommit();
        }
    }

    public function statusChanged(Order $order, string $status): void
    {
        if (isset(self::STATUS_EVENTS[$status])) {
            $this->notify($order, self::STATUS_EVENTS[$status]);
        }
    }

    /** Fill {placeholders} in an SMS template. */
    public static function render(string $template, Order $order, array $extra = []): string
    {
        $courier = ['steadfast' => 'Steadfast', 'pathao' => 'Pathao'][$order->courier] ?? null;
        $values = $extra + [
            'name' => trim($order->first_name),
            'order' => $order->order_number,
            'total' => number_format((float) $order->total),
            'amount' => number_format((float) $order->total),
            'status' => ucfirst($order->status),
            'courier' => $courier ? " with {$courier}" : '',
            'tracking_url' => $order->courier_tracking_url ?: route('track', ['order_number' => $order->order_number, 'phone' => $order->phone]),
            'site' => Setting::get('site_name'),
            'phone' => Setting::get('phone'),
        ];

        return trim(preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $template));
    }
}
