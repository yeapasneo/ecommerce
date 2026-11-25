<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['flash_sale_id', 'product_id', 'sale_price'];

    protected $casts = ['sale_price' => 'float'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
