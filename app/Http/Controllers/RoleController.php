<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected function permissionGroups(): array
    {
        $permissions = Permission::pluck('name')->all();

        $groups = [
            'Dashboard' => [],
            'POS / Sales' => [],
            'Shifts' => [],
            'Products' => [],
            'Stock' => [],
            'Suppliers & Purchases' => [],
            'Customers' => [],
            'Expenses' => [],
            'Reports & Insights' => [],
            'Activity Log' => [],
            'Administration' => [],
        ];

        $map = [
            'dashboard.' => 'Dashboard',
            'sales.' => 'POS / Sales',
            'shift.' => 'Shifts',
            'products.' => 'Products',
            'stock.' => 'Stock',
            'purchase_orders.' => 'Suppliers & Purchases',
            'suppliers.' => 'Suppliers & Purchases',
            'customers.' => 'Customers',
            'expenses.' => 'Expenses',
            'reports.' => 'Reports & Insights',
            'insights.' => 'Reports & Insights',
            'activity.' => 'Activity Log',
            'users.' => 'Administration',
            'roles.' => 'Administration',
            'branches.' => 'Administration',
            'settings.' => 'Administration',
        ];

        foreach ($permissions as $permission) {
            foreach ($map as $prefix => $group) {
                if (str_starts_with($permission, $prefix)) {
                    $groups[$group][] = $permission;
                    break;
                }
            }
        }

        return array_filter($groups);
    }

    public function index(): Response
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name'),
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        ActivityLogger::log("Created role {$role->name}", $role, 'created', [
            'permissions' => $role->permissions->pluck('name'),
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return back();
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($request->validated('name') !== $role->name) {
            $role->update(['name' => $request->validated('name')]);
        }

        $oldPermissions = $role->permissions->pluck('name')->sort()->values();
        $role->syncPermissions($request->validated('permissions'));

        ActivityLogger::log("Updated role {$role->name}", $role, 'updated', [
            'old_permissions' => $oldPermissions,
            'new_permissions' => collect($request->validated('permissions'))->sort()->values(),
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->users()->count() > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a role that has users.')]);

            return back();
        }

        if (in_array($role->name, ['Owner', 'Admin', 'Finance', 'Seller'])) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('System roles cannot be deleted.')]);

            return back();
        }

        ActivityLogger::log("Deleted role {$role->name}", $role, 'deleted', [], $request);

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return back();
    }
}
