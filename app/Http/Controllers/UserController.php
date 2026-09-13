<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['branch', 'roles'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")))
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => Role::pluck('id', 'name'),
            'branches' => Branch::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'role', 'branch_id']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        $user->syncRoles([Role::findOrFail($request->validated('role'))]);

        ActivityLogger::log("Created user {$user->name} ({$user->email})", $user, 'created', [
            'role_assigned' => $user->roles()->first()?->name,
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password']) || $data['password'] === null) {
            unset($data['password']);
        }

        $old = $user->only('name', 'email', 'phone', 'branch_id', 'is_active');

        $user->update($data);
        $user->syncRoles([Role::findOrFail($request->validated('role'))]);

        ActivityLogger::log("Updated user {$user->name} ({$user->email})", $user, 'updated', [
            'old_values' => $old,
            'new_values' => $user->only('name', 'email', 'phone', 'branch_id', 'is_active'),
            'role' => $user->roles()->first()?->name,
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete your own account.')]);

            return back();
        }

        ActivityLogger::log("Deleted user {$user->name} ({$user->email})", $user, 'deleted', [], $request);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return back();
    }
}
