<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'earn' => 'Earned', 'redeem' => 'Used at checkout', 'refund' => 'Returned (order cancelled)', 'reverse' => 'Removed (refund)',
        'referral' => 'Referral bonus', 'welcome' => 'Welcome bonus', 'adjust' => 'Adjusted by staff',
    ];

    protected $fillable = ['user_id', 'order_id', 'points', 'balance_after', 'type', 'note', 'created_by'];

    protected $casts = ['created_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
