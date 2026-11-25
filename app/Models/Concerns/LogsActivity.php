<?php

namespace App\Models\Concerns;

use App\Support\Activity;
use Illuminate\Support\Str;

/**
 * Records admin changes to a model in the activity log. Only runs for signed-in staff,
 * so customer actions (checkout, reviews…) are not logged here.
 */
trait LogsActivity
{
    protected static array $activityIgnored = ['created_at', 'updated_at', 'views', 'password', 'remember_token'];

    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => Activity::log('created', $m, 'Created '.$m->activityLabel()));
        static::updated(function ($m) {
            $changes = collect($m->getChanges())->except(static::$activityIgnored)
                ->mapWithKeys(fn ($new, $key) => [$key => [static::activityValue($m->getOriginal($key)), static::activityValue($new)]])
                ->filter(fn ($pair) => $pair[0] !== $pair[1]);
            if ($changes->isNotEmpty()) {
                Activity::log('updated', $m, 'Updated '.$m->activityLabel().': '.$changes->keys()->map(fn ($k) => str_replace('_', ' ', $k))->implode(', '), $changes->all());
            }
        });
        static::deleted(fn ($m) => Activity::log('deleted', $m, 'Deleted '.$m->activityLabel()));
    }

    /** e.g. product “Galaxy S99” */
    public function activityLabel(): string
    {
        $type = Str::lower(Str::headline(class_basename($this)));
        $name = $this->order_number ?? $this->name ?? $this->title ?? $this->code ?? $this->phone ?? $this->getKey();

        return "{$type} “{$name}”";
    }

    protected static function activityValue($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value) && mb_strlen($value) > 200) {
            return mb_substr($value, 0, 200).'…';
        }

        return $value;
    }
}
