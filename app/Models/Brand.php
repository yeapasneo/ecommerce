<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['name', 'slug', 'logo', 'is_active', 'meta_title', 'meta_description', 'meta_keywords'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['logo_url'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? media_url($this->logo) : null;
    }
}
