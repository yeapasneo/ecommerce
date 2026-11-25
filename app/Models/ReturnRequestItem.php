<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['return_request_id', 'order_item_id', 'quantity', 'serial', 'restock'];

    protected $casts = ['restock' => 'boolean', 'quantity' => 'integer'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
