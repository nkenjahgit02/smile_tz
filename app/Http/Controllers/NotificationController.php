<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private function mapNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->getKey(),
            'message' => (string) ($data['message'] ?? 'New notification'),
            'link' => $data['link'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at
                ? Carbon::parse($notification->created_at)->toDateTimeString()
                : null,
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $notifications = $this->scopedNotifications($user)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $items = collect($notifications->items())
            ->map(fn (DatabaseNotification $notification) => $this->mapNotification($notification))
            ->all();

        return Inertia::render('notifications/index', [
            'notifications' => $items,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => (int) (clone $this->scopedNotifications($user))->unread()->count(),
        ]);
    }

    /**
     * Notifications query scoped to what this user is allowed to see (Owner/Admin
     * bypass; Seller only stock/sales; Finance only finance; location-role columns
     * must match). Mirrors HandleInertiaRequests so the bell and this page agree.
     */
    private function scopedNotifications($user)
    {
        $query = $user->notifications();

        if ($user->hasAnyRole(['Owner', 'Admin'])) {
            return $query;
        }

        $locationIds = $user->scopedLocationIds();

        $query->where(function ($q) use ($user, $locationIds) {
            $q->where('target_user_id', $user->id)
                ->orWhere(function ($role) use ($user, $locationIds) {
                    $role->whereNull('target_user_id')
                        ->where(function ($r) use ($user) {
                            $r->whereNull('target_role')->orWhereIn('target_role', $user->roles()->pluck('name'));
                        })
                        ->where(function ($l) use ($locationIds) {
                            $l->whereNull('target_location_id')->orWhereIn('target_location_id', $locationIds);
                        });
                });
        });

        $query->where(function ($category) use ($user) {
            $category->whereNull('category');
            if ($user->hasRole('Seller')) {
                $category->orWhereIn('category', ['stock', 'sales']);
            } elseif ($user->hasRole('Finance')) {
                $category->orWhere('category', 'finance');
            } else {
                $category->orWhereNotNull('category');
            }
        });

        return $query;
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($notification)
            ->unread()
            ->firstOrFail()
            ->markAsRead();

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->get()->each->markAsRead();

        return redirect()->back();
    }
}
