<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq->where('order_number', 'ilike', "%{$s}%")
                ->orWhere('phone', 'ilike', "%{$s}%")->orWhere('first_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()->paginate($this->perPage())->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only('search', 'status', 'payment_status', 'from', 'to', 'per_page'),
            'statusCounts' => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load('items.product:id,slug,image', 'histories', 'user:id,name,email'),
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
        ]);
    }

    public function update(Request $request, Order $order, OrderService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'payment_status' => ['required', Rule::in(Order::PAYMENT_STATUSES)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($order->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()->with('error', 'Cancelled orders cannot be re-opened.');
        }
        $order->update(['payment_status' => $data['payment_status']]);
        $service->changeStatus($order, $data['status'], $data['comment'] ?? null);

        return back()->with('success', 'Order updated.');
    }

    public function invoice(Order $order)
    {
        return Inertia::render('Admin/Orders/Invoice', [
            'order' => $order->load('items'),
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
        ]);
    }
}
