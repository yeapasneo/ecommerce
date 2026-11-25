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

    /** Unit cost of a sold item: the cost recorded at sale, else the current variant/product cost. */
    protected const COST = 'coalesce(order_items.cost_price, pv.cost_price, p.cost_price)';

    /** Sold items in the period (cancelled orders excluded), with the tables needed for cost. */
    protected function items(Carbon $from, Carbon $to)
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'order_items.variant_id')
            ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'cancelled');
    }

    /** Revenue, cost and profit columns for grouped item queries. */
    protected static function profitColumns(): string
    {
        $cost = self::COST;

        return "sum(order_items.quantity) as qty, sum(order_items.total) as revenue, sum(order_items.quantity * {$cost}) as cost, bool_and({$cost} is not null) as cost_known";
    }

    protected static function withProfit($rows)
    {
        return collect($rows)->map(function ($r) {
            $known = (bool) $r->cost_known;
            $revenue = (float) $r->revenue;
            $profit = $known ? $revenue - (float) $r->cost : null;

            return ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => $revenue, 'profit' => $profit, 'margin' => $known && $revenue > 0 ? round($profit / $revenue * 100, 1) : null];
        })->values();
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
        $cost = self::COST;
        $c = $this->items($from, $to)->selectRaw("coalesce(sum(order_items.total),0) as sales,
            coalesce(sum(case when {$cost} is not null then order_items.total end),0) as known_sales,
            coalesce(sum(order_items.quantity * {$cost}),0) as cogs")->first();
        $sales = (float) $c->sales;
        $share = $sales > 0 ? (float) $c->known_sales / $sales : 0;
        // Order discounts are shared across items by value; delivery and VAT are passed through, not profit.
        $netKnown = (float) $c->known_sales - (float) $row->discount * $share;
        $profit = $netKnown - (float) $c->cogs;

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
            'cost' => (float) $c->cogs,
            'profit' => round($profit, 2),
            'margin' => $netKnown > 0 ? round($profit / $netKnown * 100, 1) : null,
            'costCoverage' => (int) round($share * 100),
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
            'topProducts' => self::withProfit($this->items($from, $to)->selectRaw('max(order_items.name) as name, '.self::profitColumns())
                ->groupBy('order_items.product_id')->orderByDesc('revenue')->limit(10)->get()),
            'profitable' => self::withProfit($this->items($from, $to)->selectRaw('max(order_items.name) as name, '.self::profitColumns())
                ->groupBy('order_items.product_id')->havingRaw('bool_and('.self::COST.' is not null)')
                ->orderByRaw('sum(order_items.total) - sum(order_items.quantity * '.self::COST.') desc')->limit(10)->get()),
            'categories' => self::withProfit($this->items($from, $to)->join('categories', 'categories.id', '=', 'p.category_id')
                ->selectRaw('categories.name, '.self::profitColumns())->groupBy('categories.name')->orderByDesc('revenue')->limit(10)->get()),
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
                fputcsv($out, ['Product', 'Variant', 'SKU', 'Quantity sold', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Orders']);
                $this->items($from, $to)
                    ->selectRaw('max(order_items.name) as name, order_items.variant_name, max(order_items.sku) as sku, count(distinct orders.id) as orders, '.self::profitColumns())
                    ->groupBy('order_items.product_id', 'order_items.variant_name')->orderByDesc('revenue')
                    ->get()->each(function ($r) use ($out) {
                        $known = (bool) $r->cost_known;
                        $profit = $known ? (float) $r->revenue - (float) $r->cost : null;
                        fputcsv($out, [$r->name, $r->variant_name, $r->sku, $r->qty, $r->revenue, $known ? round((float) $r->cost, 2) : '', $known ? round($profit, 2) : '',
                            $known && $r->revenue > 0 ? round($profit / $r->revenue * 100, 1) : '', $r->orders]);
                    });
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
