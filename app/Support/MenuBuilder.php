<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class MenuBuilder
{
    public static function for(User $user, array $counts = []): array
    {
        $menu = [
            'Operations' => [
                ['title' => 'Dashboard', 'href' => self::route('dashboard'), 'icon' => 'layout-dashboard', 'permissions' => ['dashboard.view']],
                ['title' => 'POS / Checkout', 'href' => self::route('pos.index'), 'icon' => 'shopping-cart', 'permissions' => ['sales.create']],
                ['title' => 'Sales', 'href' => self::route('sales.index'), 'icon' => 'receipt', 'permissions' => ['sales.view']],
                ['title' => 'Parked Sales', 'href' => self::route('parked-sales.index'), 'icon' => 'archive', 'permissions' => ['sales.park']],
                ['title' => 'Shifts', 'href' => self::route('shifts.index'), 'icon' => 'clock', 'permissions' => ['shift.open', 'shift.close']],
            ],
            'Catalog & Stock' => [
                ['title' => 'Products', 'href' => self::route('products.index'), 'icon' => 'package', 'permissions' => ['products.view']],
                ['title' => 'Catalog', 'href' => self::route('catalog.index'), 'icon' => 'tags', 'permissions' => ['catalog.manage']],
                ['title' => 'Stock', 'href' => self::route('stock.index'), 'icon' => 'warehouse', 'permissions' => ['stock.view']],
                ['title' => 'Stock Requests', 'href' => self::route('stock.requests'), 'icon' => 'package-check', 'permissions' => ['stock.restock.approve'], 'badgeKey' => 'pendingStockRequests'],
                ['title' => 'Stock Transfers', 'href' => self::route('stock-transfers.index'), 'icon' => 'arrow-left-right', 'permissions' => ['stock.transfer']],
                ['title' => 'Stock Takes', 'href' => self::route('stock-takes.index'), 'icon' => 'clipboard-check', 'permissions' => ['stock.take', 'stock.take.approve']],
                ['title' => 'Suppliers', 'href' => self::route('suppliers.index'), 'icon' => 'truck', 'permissions' => ['suppliers.manage']],
                ['title' => 'Purchase Orders', 'href' => self::route('purchase-orders.index'), 'icon' => 'clipboard-list', 'permissions' => ['purchase_orders.manage']],
                ['title' => 'Coupons', 'href' => self::route('coupons.index'), 'icon' => 'ticket-percent', 'permissions' => ['coupons.manage']],
            ],
            'Orders' => [
                ['title' => 'Branch Orders', 'href' => self::route('orders.index'), 'icon' => 'truck', 'permissions' => ['orders.view'], 'badgeKey' => 'pendingOrders'],
                ['title' => 'New Order', 'href' => self::route('orders.create'), 'icon' => 'plus-square', 'permissions' => ['orders.create']],
                ['title' => 'Approval Rules', 'href' => self::route('orders.rules'), 'icon' => 'sliders-horizontal', 'permissions' => ['orders.approve']],
            ],
            'Customers' => [
                ['title' => 'Customers', 'href' => self::route('customers.index'), 'icon' => 'users', 'permissions' => ['customers.view']],
                ['title' => 'Layaways', 'href' => self::route('layaways.index'), 'icon' => 'hand-coins', 'permissions' => ['sales.layaway']],
                ['title' => 'Loyalty Rewards', 'href' => self::route('loyalty-rewards.index'), 'icon' => 'gift', 'permissions' => ['customers.loyalty']],
            ],
            'Finance' => [
                ['title' => 'Reconciliation', 'href' => self::route('finance.reconciliation'), 'icon' => 'scale', 'permissions' => ['approve shift reconciliation']],
                ['title' => 'Cash Drawer', 'href' => self::route('finance.drawer'), 'icon' => 'wallet', 'permissions' => ['view cash drawer logs']],
                ['title' => 'Refund Approvals', 'href' => self::route('finance.refunds'), 'icon' => 'rotate-ccw', 'permissions' => ['approve refunds'], 'badgeKey' => 'pendingRefunds'],
                ['title' => 'Void Audit', 'href' => self::route('finance.voids'), 'icon' => 'shield-alert', 'permissions' => ['view void logs']],
                ['title' => 'Audit Trail', 'href' => self::route('finance.audit'), 'icon' => 'scroll-text', 'permissions' => ['view audit trails']],
                ['title' => 'Financial Reports', 'href' => self::route('finance.reports'), 'icon' => 'bar-chart-3', 'permissions' => ['view financial reports']],
                ['title' => 'Tax Settings', 'href' => self::route('finance.tax'), 'icon' => 'percent', 'permissions' => ['manage tax settings']],
                ['title' => 'Accounting (AR/AP)', 'href' => self::route('finance.accounting'), 'icon' => 'landmark', 'permissions' => ['manage accounts receivable', 'manage supplier invoices']],
                ['title' => 'Finance Policy', 'href' => self::route('finance.policy'), 'icon' => 'sliders-horizontal', 'permissions' => ['manage discount policy']],
            ],
            'Administration' => [
                ['title' => 'Activity Log', 'href' => self::route('activity.index'), 'icon' => 'scroll-text', 'permissions' => ['activity.view']],
                ['title' => 'Users', 'href' => self::route('users.index'), 'icon' => 'user-cog', 'permissions' => ['users.manage']],
                ['title' => 'Roles & Permissions', 'href' => self::route('roles.index'), 'icon' => 'shield', 'permissions' => ['roles.manage']],
                ['title' => 'Branches', 'href' => self::route('branches.index'), 'icon' => 'building-2', 'permissions' => ['branches.manage']],
                ['title' => 'Settings', 'href' => self::route('settings.index'), 'icon' => 'settings', 'permissions' => ['settings.manage']],
                ['title' => 'Receipt Printer', 'href' => self::route('settings.receipt'), 'icon' => 'printer', 'permissions' => ['settings.manage']],
            ],
        ];

        $sections = [];

        foreach ($menu as $section => $items) {
            $visible = array_values(array_filter(
                $items,
                fn (array $item) => $item['href'] !== null && $user->canAny(array_filter($item['permissions'])),
            ));

            if ($visible !== []) {
                $sections[] = [
                    'title' => $section,
                    'items' => array_map(
                        function (array $item) use ($counts) {
                            $badgeKey = $item['badgeKey'] ?? null;
                            $item = array_diff_key($item, ['permissions' => true, 'badgeKey' => true]);
                            if ($badgeKey !== null && ($counts[$badgeKey] ?? 0) > 0) {
                                $item['badge'] = $counts[$badgeKey];
                            }

                            return $item;
                        },
                        $visible,
                    ),
                ];
            }
        }

        return $sections;
    }

    protected static function route(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }
}
