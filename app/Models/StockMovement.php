<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'initial' => 'Opening stock',
        'sale' => 'Sold',
        'order_edit' => 'Order edited',
        'cancel' => 'Order cancelled',
        'return' => 'Returned',
        'purchase' => 'Purchase received',
        'adjustment' => 'Manual change',
        'import' => 'Bulk import',
    ];

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
