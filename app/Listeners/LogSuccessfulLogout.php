<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Spatie\Activitylog\Facades\Activity;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $activity = Activity::event('logout')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'role' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
                'branch_id' => $user->branch_id ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('User logged out');

        $activity->update([
            'branch_id' => $user->branch_id,
            'role' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
        ]);
    }
}
