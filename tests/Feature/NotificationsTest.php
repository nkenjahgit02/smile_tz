<?php

use App\Models\Branch;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('shows the notifications page with persisted notifications', function () {
    $owner = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
    ]);
    $owner->assignRole(['Owner']);

    $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => SystemNotification::class,
        'data' => ['message' => 'Stock is running low', 'link' => '/stock', 'time' => now()->toDateTimeString()],
    ]);

    $this->actingAs($owner)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('pagination.total', 1)
            ->where('notifications.0.message', 'Stock is running low')
            ->where('notifications.0.read', false)
            ->where('unread_count', 1));
});

it('marks a single notification as read', function () {
    $owner = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
    ]);
    $owner->assignRole(['Owner']);

    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => SystemNotification::class,
        'data' => ['message' => 'Low stock alert', 'time' => now()->toDateTimeString()],
    ]);

    $this->actingAs($owner)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $owner = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
    ]);
    $owner->assignRole(['Owner']);

    foreach (range(1, 3) as $i) {
        $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SystemNotification::class,
            'data' => ['message' => "Alert {$i}", 'time' => now()->toDateTimeString()],
        ]);
    }

    $this->actingAs($owner)
        ->post('/notifications/read-all')
        ->assertRedirect();

    expect($owner->unreadNotifications()->count())->toBe(0);
});

it('exposes the unread badge to the dashboard', function () {
    $owner = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
    ]);
    $owner->assignRole(['Owner']);

    $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => SystemNotification::class,
        'data' => ['message' => 'New sale', 'time' => now()->toDateTimeString()],
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.latest.0.message', 'New sale'));
});
