<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'session_id', 'product_id', 'variant_id', 'quantity'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Price per unit, including any flash sale. */
    public function unitPrice(): float
    {
        if ($this->variant) {
            $this->variant->setRelation('product', $this->product);

            return $this->variant->price;
        }

        return (float) $this->product->price;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
