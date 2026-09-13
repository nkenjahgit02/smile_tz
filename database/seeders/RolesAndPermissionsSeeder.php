<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'general' => ['dashboard.view'],
            'pos' => ['sales.create', 'sales.view', 'sales.refund', 'sales.void', 'sales.price_override', 'sales.discount', 'sales.layaway', 'sales.park', 'sales.split_bill', 'coupons.manage'],
            'shifts' => ['shift.open', 'shift.close', 'shift.cash_in_out'],
            'products' => ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export'],
            'catalog' => ['catalog.manage'],
            'stock' => ['stock.view', 'stock.adjust', 'stock.transfer', 'stock.take', 'stock.take.approve', 'stock.wastage', 'stock.restock.approve'],
            'orders' => [
                'orders.view', 'orders.create', 'orders.approve', 'orders.receive',
                'orders.review', 'orders.cancel', 'godown.picking', 'godown.dispatch',
            ],
            'suppliers' => ['suppliers.manage'],
            'purchases' => ['purchase_orders.manage', 'purchase_orders.approve'],
            'customers' => ['customers.view', 'customers.manage', 'customers.credit', 'customers.loyalty', 'customers.marketing'],
            'expenses' => ['expenses.view', 'expenses.create', 'expenses.approve', 'expenses.export'],
            'reports' => ['reports.view', 'reports.export', 'view-finance-reports'],
            'activity' => ['activity.view', 'activity.export'],
            'finance' => [
                'view cash drawer logs',
                'approve shift reconciliation',
                'manage petty cash',
                'approve refunds',
                'view void logs',
                'view audit trails',
                'view financial reports',
                'export sales reports',
                'view profit margins',
                'manage tax settings',
                'manage accounts receivable',
                'manage supplier invoices',
                'manage discount policy',
            ],
            'admin' => ['users.manage', 'roles.manage', 'branches.manage', 'settings.manage', 'manage-backups', 'view-audit-access-logs'],
            'insights' => ['insights.view'],
            'locations' => ['view-all-locations'],
            'safety' => ['sales.delete'],
        ];

        $all = [];
        foreach ($permissions as $permissionsList) {
            foreach ($permissionsList as $name) {
                $all[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->name;
            }
        }

        $roles = [
            'Owner' => [
                'dashboard.view', 'sales.create', 'sales.view', 'sales.refund', 'sales.void', 'sales.price_override',
                'sales.discount', 'sales.layaway', 'sales.park', 'sales.split_bill', 'coupons.manage', 'shift.open', 'shift.close', 'shift.cash_in_out',
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export',
                'catalog.manage',
                'stock.view', 'stock.adjust', 'stock.transfer', 'stock.take', 'stock.take.approve', 'stock.wastage', 'stock.restock.approve',
                'orders.view', 'orders.create', 'orders.approve', 'orders.receive', 'orders.review', 'orders.cancel', 'godown.picking', 'godown.dispatch',
                'suppliers.manage', 'purchase_orders.manage', 'purchase_orders.approve',
                'customers.view', 'customers.manage', 'customers.credit', 'customers.loyalty', 'customers.marketing',
                'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.export',
                'reports.view', 'reports.export', 'activity.view', 'activity.export',
                'users.manage', 'roles.manage', 'branches.manage', 'settings.manage', 'insights.view',
                'view-finance-reports', 'view-all-locations', 'manage-backups', 'view-audit-access-logs', 'sales.delete',
            ],
            'Admin' => [
                'dashboard.view', 'sales.create', 'sales.view', 'sales.refund', 'sales.void', 'sales.price_override',
                'sales.discount', 'sales.layaway', 'sales.park', 'sales.split_bill', 'coupons.manage', 'shift.open', 'shift.close', 'shift.cash_in_out',
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export',
                'catalog.manage',
                'stock.view', 'stock.adjust', 'stock.transfer', 'stock.take', 'stock.take.approve', 'stock.wastage', 'stock.restock.approve',
                'orders.view', 'orders.create', 'orders.approve', 'orders.receive', 'orders.review', 'orders.cancel', 'godown.picking', 'godown.dispatch',
                'suppliers.manage', 'purchase_orders.manage', 'purchase_orders.approve',
                'customers.view', 'customers.manage', 'customers.credit', 'customers.loyalty', 'customers.marketing',
                'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.export',
                'reports.view', 'reports.export', 'activity.view', 'activity.export',
                'users.manage', 'branches.manage',
                'view-finance-reports', 'view-all-locations',
                'view cash drawer logs', 'approve shift reconciliation', 'manage petty cash',
                'approve refunds', 'view void logs', 'view audit trails',
                'view financial reports', 'export sales reports', 'view profit margins',
                'manage tax settings', 'manage accounts receivable', 'manage supplier invoices',
                'manage discount policy',
                'manage-backups', 'view-audit-access-logs',
            ],
            'Finance' => [
                'dashboard.view', 'sales.view',
                'customers.view', 'customers.credit',
                'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.export',
                'reports.view', 'reports.export', 'insights.view',
                'view-finance-reports', 'view-all-locations',
                'view cash drawer logs', 'approve shift reconciliation', 'manage petty cash',
                'approve refunds', 'view void logs', 'view audit trails',
                'view financial reports', 'export sales reports', 'view profit margins',
                'manage tax settings', 'manage accounts receivable', 'manage supplier invoices',
                'manage discount policy',
            ],
            'Seller' => [
                'dashboard.view', 'sales.create', 'sales.view', 'sales.split_bill', 'sales.refund',
                'shift.open', 'shift.close', 'shift.cash_in_out', 'sales.park', 'sales.discount',
                'products.view', 'stock.view', 'customers.view', 'customers.manage',
                'orders.view', 'orders.create', 'orders.receive',
            ],
            'branch-manager' => [
                'dashboard.view', 'sales.view', 'products.view', 'stock.view', 'stock.take', 'stock.take.approve',
                'customers.view', 'reports.view', 'reports.export', 'activity.view',
                'orders.view', 'orders.create', 'orders.approve', 'orders.receive', 'orders.review', 'orders.cancel',
            ],
            'godown-staff' => [
                'dashboard.view', 'products.view', 'stock.view',
                'orders.view', 'godown.picking', 'godown.dispatch',
            ],
            'godown-manager' => [
                'dashboard.view', 'products.view', 'stock.view', 'stock.adjust', 'stock.take', 'stock.take.approve',
                'reports.view', 'reports.export',
                'orders.view', 'orders.approve', 'orders.review', 'orders.cancel', 'godown.picking', 'godown.dispatch',
            ],
        ];

        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(array_values(array_unique($permissionNames)));
        }
    }
}
