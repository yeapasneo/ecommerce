<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonPeriod;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $start = now()->subDays(29)->startOfDay();
        $daily = Order::where('created_at', '>=', $start)->where('status', '!=', 'cancelled')
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, SUM(total) as revenue, COUNT(*) as orders")
            ->groupBy('day')->get()->keyBy('day');
        $chart = collect(CarbonPeriod::create($start, now()))->map(fn ($d) => [
            'date' => $d->format('M d'),
            'revenue' => (float) ($daily[$d->format('Y-m-d')]->revenue ?? 0),
            'orders' => (int) ($daily[$d->format('Y-m-d')]->orders ?? 0),
        ])->values();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue' => (float) Order::where('status', '!=', 'cancelled')->sum('total'),
                'revenueMonth' => (float) Order::where('status', '!=', 'cancelled')->where('created_at', '>=', now()->startOfMonth())->sum('total'),
                'orders' => Order::count(),
                'pendingOrders' => Order::where('status', 'pending')->count(),
                'products' => Product::count(),
                'outOfStock' => Product::where('stock_status', 'out_of_stock')->count(),
                'customers' => User::where('is_admin', false)->count(),
                'pendingReviews' => Review::where('is_approved', false)->count(),
                'unansweredQuestions' => Question::whereNull('answer')->count(),
                'unreadMessages' => ContactMessage::where('is_read', false)->count(),
            ],
            'chart' => $chart,
            'ordersByStatus' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status'),
            'recentOrders' => Order::latest()->take(8)->get(['id', 'order_number', 'first_name', 'last_name', 'total', 'status', 'created_at']),
            'lowStock' => Product::where('stock_status', 'in_stock')->where('stock', '<=', 5)->orderBy('stock')->take(8)->get(['id', 'name', 'sku', 'stock', 'image']),
            'topProducts' => OrderItem::selectRaw('name, SUM(quantity) as qty, SUM(total) as revenue')
                ->groupBy('name')->orderByDesc('qty')->take(5)->get(),
        ]);
    }
}
