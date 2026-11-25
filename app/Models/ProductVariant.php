<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use LogsActivity;

    protected $fillable = ['product_id', 'name', 'options', 'sku', 'price', 'regular_price', 'cost_price', 'stock', 'image', 'is_active', 'sort_order'];

    protected $hidden = ['cost_price'];

    protected $casts = ['options' => 'array', 'regular_price' => 'float', 'cost_price' => 'float', 'is_active' => 'boolean', 'stock' => 'integer'];

    protected $appends = ['image_url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** During a flash sale the variant gets the same percentage off as its product. */
    public function getPriceAttribute($value): float
    {
        $price = (float) $value;
        $product = $this->relationLoaded('product') ? $this->product : null;
        if ($product && $product->flashSale()) {
            $base = $product->basePrice();

            return $base > 0 ? round($price * $product->price / $base) : $price;
        }

        return $price;
    }

    public function basePrice(): float
    {
        return (float) ($this->attributes['price'] ?? 0);
    }

    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->attributes['image'] ?? null);
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && ($this->stock > 0 || $this->product?->stock_status === 'pre_order');
    }
}
