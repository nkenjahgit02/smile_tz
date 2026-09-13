<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;
use Spatie\Activitylog\Facades\Activity;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        $activity = Activity::event('login')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'role' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
                'branch_id' => $user->branch_id ?? null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'via_remember' => $event->remember,
            ])
            ->log('User logged in');

        $activity->update([
            'branch_id' => $user->branch_id,
            'role' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
        ]);
    }
}
