<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

class SystemNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    private string $token;

    public function __construct(
        public string $message,
        public ?string $link = null,
        public ?string $category = null,
        public ?string $target_role = null,
        public ?int $target_user_id = null,
        public ?int $target_location_id = null,
        public ?string $type = null,
    ) {
        $this->token = (string) Str::uuid();
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'link' => $this->link,
            'time' => now()->toDateTimeString(),
            'token' => $this->token,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'system.notification';
    }

    public function broadcastType(): string
    {
        return 'system';
    }

    /**
     * Send this notification to one or more users and back-fill the targeting
     * columns (category / target_role / target_user_id / target_location_id)
     * on the rows we just created. Laravel's database channel ignores custom
     * columns, so the patch happens here right after the insert.
     */
    public function deliver(User|Collection $to): void
    {
        $recipients = $to instanceof Collection ? $to : collect([$to]);
        $recipients = $recipients->reject(fn ($user) => ! $user)->values();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients->all(), $this);

        DatabaseNotification::query()
            ->where('type', self::class)
            ->where('data', 'like', '%'.$this->token.'%')
            ->update([
                'category' => $this->category,
                'target_role' => $this->target_role,
                'target_user_id' => $this->target_user_id,
                'target_location_id' => $this->target_location_id,
                'finance_type' => $this->type,
            ]);
    }
}
