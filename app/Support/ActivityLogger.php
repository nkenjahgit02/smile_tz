<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Facades\Activity;

class ActivityLogger
{
    public static function log(
        string $description,
        ?Model $subject = null,
        ?string $event = null,
        array $extra = [],
        ?Request $request = null,
    ): void {
        $request ??= request();
        $user = $request?->user();

        $properties = array_merge([
            'role' => $user?->activeRoleName(),
            'branch_id' => $user?->branch_id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ], $extra);

        $builder = Activity::causedBy($user)
            ->performedOn($subject)
            ->withProperties($properties);

        if ($event !== null) {
            $builder->event($event);
        }

        $activity = $builder->log($description);

        if ($user) {
            $activity->update([
                'branch_id' => $user->branch_id,
                'role' => $user->activeRoleName(),
            ]);
        }
    }
}
