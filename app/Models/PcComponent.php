<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcComponent extends Model
{
    protected $fillable = ['name', 'slug', 'category_id', 'is_required', 'section', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
