<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedPhone extends Model
{
    protected $fillable = ['phone', 'reason', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function isBlocked(?string $phone): bool
    {
        return $phone && static::where('phone', Phone::normalize($phone))->exists();
    }
}
