<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCheckout extends Model
{
    protected $fillable = ['session_id', 'user_id', 'name', 'phone', 'email', 'items', 'total', 'token', 'reminded_at', 'recovered_at', 'order_id'];

    protected $casts = ['items' => 'array', 'total' => 'float', 'reminded_at' => 'datetime', 'recovered_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
