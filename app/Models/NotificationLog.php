<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = ['order_id', 'channel', 'event', 'recipient', 'message', 'status', 'error'];
}
