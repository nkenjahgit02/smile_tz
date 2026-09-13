<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Request;
use Spatie\Activitylog\Facades\Activity;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $user = $event->user;

        $activity = Activity::event('failed_login')
            ->withProperties([
                'email' => $user?->email ?? $event->credentials['email'] ?? null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ])
            ->log('Failed login attempt');

        if ($user) {
            $activity->update([
                'branch_id' => $user->branch_id,
                'role' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
            ]);
        }
    }
}
