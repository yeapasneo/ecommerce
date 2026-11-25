<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'slug', 'description', 'image', 'link', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = ['starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d', 'is_active' => 'boolean'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? media_url($this->image) : null;
    }
}
