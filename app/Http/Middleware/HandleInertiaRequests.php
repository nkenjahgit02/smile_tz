<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\BranchOrder;
use App\Models\RefundRequest;
use App\Models\RestockRequest;
use App\Support\MenuBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'email', 'phone', 'branch_id', 'is_active'),
                    'avatar' => null,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'branch' => $user->branch?->only('id', 'name', 'code', 'currency'),
                ] : null,
            ],
            'notifications' => $user ? $this->notificationsSnapshot($user) : ['unread_count' => 0, 'latest' => []],
            'nav' => [
                'sections' => $user ? MenuBuilder::for($user, $this->menuCounts($user)) : [],
            ],
            'branches' => $user?->can('branches.manage') || $user?->can('users.manage')
                ? Branch::select('id', 'name', 'code')->where('is_active', true)->get()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function menuCounts($user): array
    {
        return [
            'pendingStockRequests' => $user->can('stock.restock.approve')
                ? RestockRequest::pending()->count()
                : 0,
            'pendingRefunds' => $user->can('approve refunds')
                ? RefundRequest::pending()->whereIn('branch_id', $user->scopedLocationIds())->count()
                : 0,
            'pendingOrders' => $user->can('orders.view')
                ? BranchOrder::query()
                    ->whereIn('status', ['pending_approval', 'picking', 'ready_for_dispatch', 'dispatched', 'discrepancy_review', 'partially_received'])
                    ->where(fn ($q) => $q->whereIn('branch_id', $user->scopedLocationIds())->orWhereIn('godown_id', $user->scopedLocationIds()))
                    ->count()
                : 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roleNames($user): array
    {
        return $user->roles()->pluck('name')->all();
    }

    /**
     * Notifications query scoped to what this user is allowed to see. Owner and
     * Admin bypass every filter (rows are already targeted to recipients at
     * creation time); Seller may only see stock/sales notifications, Finance
     * only finance notifications, and location-role columns must match.
     */
    private function scopedNotifications($user)
    {
        $query = $user->notifications();

        // Owner/Admin see everything they ever received.
        if ($user->hasAnyRole(['Owner', 'Admin'])) {
            return $query;
        }

        $locationIds = $user->scopedLocationIds();

        $query->where(function ($q) use ($user, $locationIds) {
            $q->where('target_user_id', $user->id)
                ->orWhere(function ($role) use ($user, $locationIds) {
                    $role->whereNull('target_user_id')
                        ->where(function ($r) use ($user) {
                            $r->whereNull('target_role')->orWhereIn('target_role', $this->roleNames($user));
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

    /**
     * @return array{unread_count: int, latest: array<int, array<string, mixed>>}
     */
    private function notificationsSnapshot($user): array
    {
        $notifications = $this->scopedNotifications($user);

        return [
            'unread_count' => (int) (clone $notifications)->unread()->count(),
            'latest' => (clone $notifications)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->getKey(),
                    'message' => (string) ($notification->data['message'] ?? 'New notification'),
                    'link' => $notification->data['link'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toDateTimeString(),
                ])
                ->all(),
        ];
    }
}
