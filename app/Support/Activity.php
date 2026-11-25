<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Who changed what in the admin panel. */
class Activity
{
    public static function log(string $action, ?Model $subject, string $description, ?array $changes = null): void
    {
        $user = auth()->user();
        if (! $user?->is_admin) {
            return;
        }
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'changes' => $changes ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
