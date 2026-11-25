<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcComponent extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'slug', 'category_id', 'is_required', 'section', 'sort_order'];

    protected $casts = ['is_required' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
