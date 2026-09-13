<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class PushTestNotifications extends Command
{
    protected $signature = 'notify:test
        {--count=10 : How many notifications to push}
        {--every=6 : Seconds between each push}';

    protected $description = 'Push a test notification to dashboard users every few seconds over Reverb';

    public function handle(): int
    {
        $users = User::permission('dashboard.view')->get();

        if ($users->isEmpty()) {
            $users = User::where('is_active', true)->get();
        }

        if ($users->isEmpty()) {
            $this->error('No active users found to notify.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $every = max(0, (int) $this->option('every'));

        $this->info("Pushing {$count} test notifications to ".$users->count().' user(s).');

        for ($i = 1; $i <= $count; $i++) {
            $message = sprintf(
                'Test notification #%d · %s',
                $i,
                now()->format('H:i:s'),
            );

            try {
                Notification::send($users, new SystemNotification($message, '/dashboard'));
                $this->info("  pushed #{$i} => {$message}");
            } catch (\Throwable $e) {
                $this->warn("  #{$i} stored but broadcast failed ({$e->getMessage()})");
            }

            if ($i < $count && $every > 0) {
                sleep($every);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
