<x-mail::message>
# {{ $forAdmin ? 'New order' : 'Hi '.$order->first_name.',' }}

{{ $intro }}

@if ($courierLine)
{{ $courierLine }}
@endif

<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | ৳{{ number_format($item->total) }} |
@endforeach
| Delivery | | ৳{{ number_format($order->delivery_charge) }} |
@if ($order->discount > 0)
| Discount | | -৳{{ number_format($order->discount) }} |
@endif
| **Total** | | **৳{{ number_format($order->total) }}** |
</x-mail::table>

**Order #{{ $order->order_number }}** · {{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }} · {{ ucfirst($order->payment_status) }}<br>
Deliver to: {{ $order->address }}, {{ $order->city }}

<x-mail::button :url="$buttonUrl">
{{ $buttonText }}
</x-mail::button>

@unless ($forAdmin)
Questions? Call us at {{ $contact }}.
@endunless

Thanks,<br>
{{ $site }}
</x-mail::message>
