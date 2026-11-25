<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\FlashSales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => FlashSales::forget());
        static::deleted(fn () => FlashSales::forget());
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function isRunning(): bool
    {
        return $this->is_active && now()->between($this->starts_at, $this->ends_at);
    }
}
