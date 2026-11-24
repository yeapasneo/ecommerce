<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const STOCK_STATUSES = [
        'in_stock' => 'In Stock',
        'out_of_stock' => 'Out of Stock',
        'pre_order' => 'Pre Order',
        'up_coming' => 'Up Coming',
    ];

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'model', 'price', 'regular_price', 'stock', 'stock_status',
        'key_features', 'description', 'image', 'warranty_months', 'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    protected $casts = [
        'key_features' => 'array',
        'price' => 'float',
        'regular_price' => 'float',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected $appends = ['image_url', 'discount', 'stock_label'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->attributes['image'] ?? null);
    }

    public function getDiscountAttribute(): float
    {
        $regular = $this->attributes['regular_price'] ?? null;
        $price = $this->attributes['price'] ?? null;

        return $regular && $price !== null && $regular > $price ? (float) $regular - (float) $price : 0;
    }

    public function getStockLabelAttribute(): ?string
    {
        $status = $this->attributes['stock_status'] ?? null;

        return $status ? (self::STOCK_STATUSES[$status] ?? $status) : null;
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && in_array($this->stock_status, ['in_stock', 'pre_order'], true);
    }

    /** Lightweight array used by product cards. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'regular_price' => $this->regular_price,
            'discount' => $this->discount,
            'image_url' => $this->image_url,
            'stock_status' => $this->stock_status,
            'stock_label' => $this->stock_label,
            'key_features' => array_slice($this->key_features ?? [], 0, 4),
        ];
    }
}
