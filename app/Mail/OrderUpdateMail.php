<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderUpdateMail extends Mailable
{
    public const LINES = [
        'order_placed' => ['Order #%s received', 'Thank you for your order. We will call you shortly to confirm it.'],
        'order_confirmed' => ['Order #%s confirmed', 'Your order is confirmed and we are packing it now.'],
        'order_shipped' => ['Order #%s is on the way', 'Your order has been handed to the courier.'],
        'order_delivered' => ['Order #%s delivered', 'Your order has been delivered. We hope you enjoy it!'],
        'order_cancelled' => ['Order #%s cancelled', 'Your order has been cancelled. If this is unexpected, please contact us.'],
        'payment_received' => ['Payment received for order #%s', 'We have received your payment. Thank you!'],
    ];

    public function __construct(public Order $order, public string $event, public bool $forAdmin = false) {}

    public function subjectLine(): string
    {
        if ($this->forAdmin) {
            return "New order #{$this->order->order_number} — ৳".number_format((float) $this->order->total);
        }

        return sprintf(self::LINES[$this->event][0] ?? 'Update on order #%s', $this->order->order_number).' | '.Setting::get('site_name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        $order = $this->order;

        return new Content(markdown: 'emails.order-update', with: [
            'order' => $order,
            'intro' => $this->forAdmin
                ? "{$order->first_name} {$order->last_name} ({$order->phone}) placed an order from {$order->city}."
                : (self::LINES[$this->event][1] ?? 'There is an update on your order.'),
            'courierLine' => $this->event === 'order_shipped' && $order->courier
                ? 'Courier: '.ucfirst($order->courier).' · Consignment '.($order->courier_tracking_code ?: $order->courier_consignment_id)
                : null,
            'buttonUrl' => $this->forAdmin
                ? route('admin.orders.show', $order)
                : ($order->courier_tracking_url ?: route('track', ['order_number' => $order->order_number, 'phone' => $order->phone])),
            'buttonText' => $this->forAdmin ? 'Open order' : ($order->courier_tracking_url ? 'Track parcel' : 'Track order'),
            'site' => Setting::get('site_name'),
            'contact' => Setting::get('phone'),
            'forAdmin' => $this->forAdmin,
        ]);
    }
}
