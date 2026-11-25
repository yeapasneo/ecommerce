<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'product_id', 'variant_id', 'name', 'quantity', 'received_quantity', 'unit_cost'];

    protected $casts = ['unit_cost' => 'float', 'quantity' => 'integer', 'received_quantity' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
