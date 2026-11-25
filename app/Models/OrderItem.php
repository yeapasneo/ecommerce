<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'variant_id', 'name', 'variant_name', 'sku', 'price', 'cost_price', 'quantity', 'total'];

    protected $hidden = ['cost_price'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    protected $casts = ['price' => 'float', 'cost_price' => 'float', 'total' => 'float'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
