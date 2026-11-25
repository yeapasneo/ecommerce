<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A return (money back) or warranty claim (repair/replace) for items of a delivered order. */
class ReturnRequest extends Model
{
    use LogsActivity;

    public const TYPES = ['return' => 'Return', 'warranty' => 'Warranty claim'];

    public const STATUSES = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'received' => 'Item received',
        'refunded' => 'Refunded',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    /** Requests still taking up the items (an item can't be claimed twice at once). */
    public const OPEN = ['requested', 'approved', 'received'];

    public const REASONS = [
        'defective' => 'Defective or not working',
        'damaged' => 'Arrived damaged',
        'wrong_item' => 'Wrong item sent',
        'not_as_described' => 'Not as described',
        'missing_parts' => 'Missing parts or accessories',
        'changed_mind' => 'Changed my mind',
        'other' => 'Other',
    ];

    public const RESOLUTIONS = ['refund' => 'Refund', 'replacement' => 'Replacement', 'repair' => 'Repair'];

    public const REFUND_METHODS = ['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank transfer', 'card' => 'Card / gateway refund'];

    protected $fillable = ['number', 'order_id', 'user_id', 'type', 'status', 'reason', 'details', 'resolution', 'staff_note',
        'refund_amount', 'refund_method', 'refund_reference', 'refunded_at', 'restocked', 'handled_by'];

    protected $casts = ['refund_amount' => 'float', 'refunded_at' => 'datetime', 'restocked' => 'boolean'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnEvent::class)->latest('id');
    }

    public static function nextNumber(): string
    {
        return 'RMA-'.str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    public function activityLabel(): string
    {
        return "return “{$this->number}”";
    }

    /** Value of the returned items at the price paid. */
    public function itemsValue(): float
    {
        return (float) $this->items->sum(fn ($i) => $i->quantity * (float) $i->orderItem?->price);
    }
}
