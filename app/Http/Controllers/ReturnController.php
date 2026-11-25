<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Services\Returns;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Customers request returns and warranty claims from their account. */
class ReturnController extends Controller
{
    public function __construct(protected Returns $returns) {}

    public function index(Request $request)
    {
        return Inertia::render('Account/Returns', [
            'returns' => ReturnRequest::where('user_id', $request->user()->id)->with(['order:id,order_number', 'items.orderItem:id,name,variant_name', 'events' => fn ($q) => $q->select('id', 'return_request_id', 'status', 'comment', 'created_at')])
                ->latest('id')->get()->map(fn ($r) => $r->only('id', 'number', 'type', 'status', 'reason', 'details', 'staff_note', 'refund_amount', 'refunded_at', 'created_at', 'order', 'items', 'events')),
            'statuses' => ReturnRequest::STATUSES,
            'types' => ReturnRequest::TYPES,
            'reasons' => ReturnRequest::REASONS,
        ]);
    }

    public function create(Request $request, string $number)
    {
        abort_unless(Setting::enabled('returns_enabled'), 404);
        $order = $request->user()->orders()->where('order_number', $number)->with('items.product:id,slug,image', 'histories')->firstOrFail();

        return Inertia::render('Account/ReturnForm', [
            'order' => $order->only('id', 'order_number', 'status', 'created_at') + ['items' => $order->items, 'delivered_at' => $order->deliveredAt()?->toIso8601String()],
            'claimable' => $this->returns->claimable($order),
            'returnDays' => Returns::returnDays(),
            'types' => ReturnRequest::TYPES,
            'reasons' => ReturnRequest::REASONS,
        ]);
    }

    public function store(Request $request, string $number)
    {
        abort_unless(Setting::enabled('returns_enabled'), 404);
        $order = $request->user()->orders()->where('order_number', $number)->firstOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReturnRequest::TYPES))],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.serial' => ['nullable', 'string', 'max:100'],
        ], ['details.min' => 'Please describe the problem in a few words.']);
        $r = $this->returns->open($order, $data['type'], $data['items'], $data['reason'], $data['details'], $request->user()->id);

        return redirect()->route('account.returns')->with('success', "Request {$r->number} sent. We'll contact you soon.");
    }

    public function cancel(Request $request, string $number)
    {
        $r = ReturnRequest::where('user_id', $request->user()->id)->where('number', $number)->firstOrFail();
        $this->returns->cancel($r, $request->user()->id);

        return back()->with('success', "Request {$r->number} cancelled.");
    }
}
