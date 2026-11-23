<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort_order'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return media_url($this->path);
    }
}
