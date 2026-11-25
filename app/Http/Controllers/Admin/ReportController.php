<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales figures for a date range. Cancelled orders are left out of sales and shown separately. */
class ReportController extends Controller
{
    protected function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $from = $request->date('from')?->startOfDay() ?? $to->copy()->subDays(29)->startOfDay();

        return [$from, $to];
    }

    protected function sales(Carbon $from, Carbon $to): Builder
    {
        return Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled');
    }

    protected function totals(Carbon $from, Carbon $to): array
    {
        $row = $this->sales($from, $to)->toBase()->selectRaw('count(*) as orders, coalesce(sum(total),0) as revenue, coalesce(sum(discount),0) as discount,
            coalesce(sum(tax),0) as tax, coalesce(sum(delivery_charge),0) as delivery, coalesce(sum(paid_amount),0) as collected,
            count(distinct right(regexp_replace(phone, \'[^0-9]\', \'\', \'g\'), 10)) as customers')->first();
        $items = (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')->sum('order_items.quantity');
        $revenue = (float) $row->revenue;
        $orders = (int) $row->orders;

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'aov' => $orders ? round($revenue / $orders) : 0,
            'items' => $items,
            'discount' => (float) $row->discount,
            'tax' => (float) $row->tax,
            'delivery' => (float) $row->delivery,
            'collected' => (float) $row->collected,
            'outstanding' => max(0, $revenue - (float) $row->collected),
            'customers' => (int) $row->customers,
        ];
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $days = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays((int) ceil($days) - 1)->startOfDay();

        $daily = $this->sales($from, $to)->toBase()->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as orders, sum(total) as revenue")
            ->groupBy('day')->pluck('revenue', 'day');
        $dailyOrders = $this->sales($from, $to)->toBase()->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as orders")->groupBy('day')->pluck('orders', 'day');
        $series = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $d->format('Y-m-d');
            $series[] = ['date' => $d->format('d M'), 'revenue' => (float) ($daily[$k] ?? 0), 'orders' => (int) ($dailyOrders[$k] ?? 0)];
        }

        $itemSales = fn () => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled');
        $breakdown = fn (string $column) => $this->sales($from, $to)->toBase()->selectRaw("{$column} as name, count(*) as orders, sum(total) as revenue")
            ->groupBy($column)->orderByDesc('revenue')->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->name, 'orders' => (int) $r->orders, 'revenue' => (float) $r->revenue]);

        $cancelled = Order::whereBetween('created_at', [$from, $to])->where('status', 'cancelled')->toBase()->selectRaw('count(*) as n, coalesce(sum(total),0) as v')->first();

        return Inertia::render('Admin/Reports', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $this->totals($from, $to),
            'previous' => $this->totals($prevFrom, $prevTo),
            'cancelled' => ['orders' => (int) $cancelled->n, 'value' => (float) $cancelled->v],
            'newCustomers' => User::where('is_admin', false)->whereBetween('created_at', [$from, $to])->count(),
            'series' => $series,
            'topProducts' => $itemSales()->selectRaw('order_items.product_id, max(order_items.name) as name, sum(order_items.quantity) as qty, sum(order_items.total) as revenue')
                ->groupBy('order_items.product_id')->orderByDesc('revenue')->limit(10)->get(),
            'categories' => $itemSales()->join('products', 'products.id', '=', 'order_items.product_id')->join('categories', 'categories.id', '=', 'products.category_id')
                ->selectRaw('categories.name, sum(order_items.quantity) as qty, sum(order_items.total) as revenue')->groupBy('categories.name')->orderByDesc('revenue')->limit(10)->get(),
            'payments' => $breakdown('payment_method')->map(fn ($r) => ['name' => Order::PAYMENT_METHODS[$r['name']] ?? $r['name']] + $r),
            'sources' => $breakdown('source')->map(fn ($r) => ['name' => Order::SOURCES[$r['name']] ?? $r['name']] + $r),
            'cities' => $breakdown('city'),
            'statuses' => Order::whereBetween('created_at', [$from, $to])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    /** CSV for Excel / Google Sheets. */
    public function export(Request $request, string $type): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $name = "{$type}-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($type, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ৳ and Bangla correctly
            if ($type === 'orders') {
                fputcsv($out, ['Order', 'Date', 'Status', 'Source', 'Customer', 'Phone', 'City', 'Items', 'Subtotal', 'Discount', 'VAT', 'Delivery', 'Total', 'Paid', 'Due', 'Payment method', 'Payment status', 'Courier', 'Consignment']);
                Order::whereBetween('created_at', [$from, $to])->withSum('items as qty', 'quantity')->orderBy('id')
                    ->chunk(500, function ($orders) use ($out) {
                        foreach ($orders as $o) {
                            fputcsv($out, [$o->order_number, $o->created_at->format('Y-m-d H:i'), $o->status, $o->source, trim($o->first_name.' '.$o->last_name), $o->phone, $o->city,
                                (int) $o->qty, $o->subtotal, $o->discount, $o->tax, $o->delivery_charge, $o->total, $o->paid_amount, $o->due_amount,
                                Order::PAYMENT_METHODS[$o->payment_method] ?? $o->payment_method, $o->payment_status, $o->courier, $o->courier_consignment_id]);
                        }
                    });
            } else {
                fputcsv($out, ['Product', 'Variant', 'SKU', 'Quantity sold', 'Revenue', 'Orders']);
                DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled')
                    ->selectRaw('max(order_items.name) as name, order_items.variant_name, max(order_items.sku) as sku, sum(order_items.quantity) as qty, sum(order_items.total) as revenue, count(distinct orders.id) as orders')
                    ->groupBy('order_items.product_id', 'order_items.variant_name')->orderByDesc('revenue')
                    ->get()->each(fn ($r) => fputcsv($out, [$r->name, $r->variant_name, $r->sku, $r->qty, $r->revenue, $r->orders]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
