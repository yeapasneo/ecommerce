<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Product text written by AI, waiting for staff to approve or reject it. */
class AiDraft extends Model
{
    protected $fillable = ['product_id', 'fields', 'status', 'error', 'reviewed_by'];

    protected $casts = ['fields' => 'array'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
