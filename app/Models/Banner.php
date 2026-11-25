<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'subtitle', 'image', 'link', 'position', 'bg_color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? media_url($this->image) : null;
    }
}
