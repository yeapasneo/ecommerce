<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpec extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'group', 'name', 'value', 'sort_order'];
}
