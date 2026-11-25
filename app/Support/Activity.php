<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Who changed what in the admin panel. */
class Activity
{
    protected static bool $paused = false;

    /** Run $callback without per-record entries (e.g. a bulk import logs one summary instead). */
    public static function quietly(callable $callback): mixed
    {
        $was = self::$paused;
        self::$paused = true;
        try {
            return $callback();
        } finally {
            self::$paused = $was;
        }
    }

    public static function log(string $action, ?Model $subject, string $description, ?array $changes = null): void
    {
        $user = auth()->user();
        if (! $user?->is_admin || self::$paused) {
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
