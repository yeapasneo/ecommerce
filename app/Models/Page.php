<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'slug', 'content', 'is_active', 'meta_title', 'meta_description', 'meta_keywords'];

    protected $casts = ['is_active' => 'boolean'];
}
