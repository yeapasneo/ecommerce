<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\Returns;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Staff side of returns, refunds and warranty claims, plus serial numbers. */
class ReturnController extends Controller
{
    public function __construct(protected Returns $returns) {}

    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->status, ReturnRequest::STATUSES) ? $request->status : null;

        return Inertia::render('Admin/Returns/Index', [
            'returns' => ReturnRequest::with('order:id,order_number,first_name,last_name,phone')->withCount('items')
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ReturnRequest::OPEN))
                ->when($request->type, fn ($q, $t) => $q->where('type', $t))
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'ilike', "%{$s}%")
                    ->orWhereHas('order', fn ($q) => $q->where('order_number', 'ilike', "%{$s}%")->orWhere('phone', 'ilike', "%{$s}%"))))
                ->latest('id')->paginate($this->perPage())->withQueryString(),
            'filters' => ['status' => $status, 'type' => $request->type, 'search' => $request->search],
            'counts' => ReturnRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'statuses' => ReturnRequest::STATUSES,
            'types' => ReturnRequest::TYPES,
        ]);
    }

    public function show(ReturnRequest $return)
    {
        $return->load(['order.items', 'order.histories', 'items.orderItem.product:id,name,slug,image', 'events.user:id,name', 'handler:id,name']);
        $order = $return->order;
        $delivered = $order->deliveredAt();

        return Inertia::render('Admin/Returns/Show', [
            'request' => $return,
            'order' => $order->only('id', 'order_number', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'total', 'paid_amount', 'refunded_amount', 'payment_method', 'payment_status', 'status', 'created_at'),
            'deliveredAt' => $delivered?->toIso8601String(),
            'warranty' => $return->items->mapWithKeys(fn ($line) => [$line->id => $this->warrantyFor($line->orderItem, $delivered)]),
            'refundable' => round(max(0, (float) $order->paid_amount - (float) $order->refunded_amount), 2),
            'suggestedRefund' => round($return->itemsValue(), 2),
            'statuses' => ReturnRequest::STATUSES,
            'types' => ReturnRequest::TYPES,
            'reasons' => ReturnRequest::REASONS,
            'resolutions' => ReturnRequest::RESOLUTIONS,
            'refundMethods' => ReturnRequest::REFUND_METHODS,
        ]);
    }

    /** Staff open a return or claim for a customer (no time limits). */
    public function create(Order $order)
    {
        $order->load('items', 'histories');

        return Inertia::render('Admin/Returns/Create', [
            'order' => $order->only('id', 'order_number', 'first_name', 'last_name', 'phone', 'status') + ['items' => $order->items, 'delivered_at' => $order->deliveredAt()?->toIso8601String()],
            'claimable' => $this->returns->claimable($order),
            'types' => ReturnRequest::TYPES,
            'reasons' => ReturnRequest::REASONS,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReturnRequest::TYPES))],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.serial' => ['nullable', 'string', 'max:100'],
        ]);
        $r = $this->returns->open($order->load('items'), $data['type'], $data['items'], $data['reason'], $data['details'] ?? null, $request->user()->id, byStaff: true);

        return redirect()->route('admin.returns.show', $r)->with('success', "{$r->number} created.");
    }

    /** One step of the workflow. */
    public function update(Request $request, ReturnRequest $return)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'receive', 'refund', 'complete'])],
            'note' => [$request->action === 'reject' ? 'required' : 'nullable', 'string', 'max:1000'],
            'resolution' => ['nullable', Rule::in(array_keys(ReturnRequest::RESOLUTIONS))],
            'restock' => ['nullable', 'array'],
            'amount' => ['required_if:action,refund', 'nullable', 'numeric', 'min:0.01'],
            'method' => ['required_if:action,refund', 'nullable', Rule::in(array_keys(ReturnRequest::REFUND_METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
        ], ['note.required' => 'Tell the customer why it was rejected.']);
        $staff = $request->user()->id;
        $note = $data['note'] ?? null;

        match ($data['action']) {
            'approve' => $this->returns->approve($return, $note, $staff, $data['resolution'] ?? null),
            'reject' => $this->returns->reject($return, $note, $staff),
            'receive' => $this->returns->receive($return, array_map('boolval', $data['restock'] ?? []), $note, $staff),
            'refund' => $this->returns->refund($return, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $note, $staff),
            'complete' => $this->returns->complete($return, $data['resolution'] ?? null, $note, $staff),
        };

        return back()->with('success', "{$return->number}: ".strtolower(ReturnRequest::STATUSES[$return->fresh()->status]).'.');
    }

    /** Serial / IMEI numbers for the units of an order line. */
    public function serials(Request $request, Order $order, OrderItem $item)
    {
        abort_unless($item->order_id === $order->id, 404);
        $serials = collect(preg_split('/[\r\n,]+/', (string) $request->validate(['serials' => ['nullable', 'string', 'max:5000']])['serials']))
            ->map(fn ($s) => Returns::cleanSerial($s))->filter()->unique()->values();
        if ($serials->count() > $item->quantity) {
            throw ValidationException::withMessages(['serials' => "Only {$item->quantity} unit(s) were sold on this line."]);
        }
        $clash = $serials->first(fn ($s) => OrderItem::whereKeyNot($item->id)->whereJsonContains('serials', $s)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->exists());
        if ($clash) {
            throw ValidationException::withMessages(['serials' => "{$clash} is already recorded on another order."]);
        }
        $item->update(['serials' => $serials->all() ?: null]);

        return back()->with('success', 'Serial numbers saved.');
    }

    /** Look up a serial / IMEI: what it is, who bought it and whether it is still under warranty. */
    public function warranty(Request $request)
    {
        $serial = Returns::cleanSerial($request->input('serial'));
        $results = [];
        if ($serial) {
            $results = OrderItem::whereJsonContains('serials', $serial)
                ->orWhereRaw('exists (select 1 from jsonb_array_elements_text(order_items.serials) s where s ilike ?)', ['%'.addcslashes($serial, '%_\\').'%'])
                ->with(['order' => fn ($q) => $q->with('histories'), 'product:id,name,slug,image'])->limit(20)->get()
                ->map(fn ($item) => [
                    'item' => $item->only('id', 'name', 'variant_name', 'sku', 'price', 'quantity', 'serials', 'warranty_months'),
                    'order' => $item->order->only('id', 'order_number', 'first_name', 'last_name', 'phone', 'status', 'created_at'),
                    'warranty' => $this->warrantyFor($item, $item->order->deliveredAt()),
                    'claims' => ReturnRequest::whereHas('items', fn ($q) => $q->where('order_item_id', $item->id))->latest('id')->get(['id', 'number', 'type', 'status', 'created_at']),
                ]);
        }

        return Inertia::render('Admin/Returns/Warranty', ['serial' => $request->input('serial'), 'results' => $results]);
    }

    protected function warrantyFor(?OrderItem $item, $delivered): array
    {
        $months = (int) $item?->warranty_months;
        $ends = $delivered && $months ? $delivered->copy()->addMonths($months) : null;

        return [
            'months' => $months,
            'delivered' => $delivered?->toDateString(),
            'ends' => $ends?->toDateString(),
            'valid' => $ends ? $ends->isFuture() : false,
            'days_left' => $ends?->isFuture() ? (int) now()->diffInDays($ends) : 0,
        ];
    }
}
