<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedPc extends Model
{
    protected $fillable = ['user_id', 'name', 'items', 'total'];

    protected $casts = ['items' => 'array', 'total' => 'float'];
}
