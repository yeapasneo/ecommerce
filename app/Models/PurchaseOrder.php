<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Partly received', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $fillable = ['number', 'supplier_id', 'status', 'expected_at', 'received_at', 'total', 'note', 'created_by'];

    protected $casts = ['expected_at' => 'date', 'received_at' => 'datetime', 'total' => 'float'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextNumber(): string
    {
        return 'PO-'.str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    public function activityLabel(): string
    {
        return "purchase order “{$this->number}”";
    }

    /** Can items still be changed? */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'ordered'], true);
    }
}
