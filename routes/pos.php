<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashDrawerController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FinancePolicyController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\LayawayController;
use App\Http\Controllers\LoyaltyRewardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ParkedSaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\RefundApprovalController;
use App\Http\Controllers\RestockRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoidAuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Users (Owner / Admin)
    Route::middleware('permission:users.manage')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::post('/', [UserController::class, 'store'])->name('users.store');
        Route::patch('/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Roles & permissions (Owner only)
    Route::middleware('permission:roles.manage')->prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Branches (Owner)
    Route::middleware('permission:branches.manage')->prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    // Activity Log (Owner / Admin / Finance)
    Route::middleware('permission:activity.view')->prefix('activity')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('/export', [ActivityController::class, 'export'])
            ->middleware('permission:activity.export')
            ->name('activity.export');
    });

    // Catalog reference data (Owner / Admin)
    Route::middleware('permission:catalog.manage')->prefix('catalog')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/categories', [CatalogController::class, 'storeCategory'])->name('catalog.categories.store');
        Route::patch('/categories/{category}', [CatalogController::class, 'updateCategory'])->name('catalog.categories.update');
        Route::delete('/categories/{category}', [CatalogController::class, 'destroyCategory'])->name('catalog.categories.destroy');
        Route::post('/brands', [CatalogController::class, 'storeBrand'])->name('catalog.brands.store');
        Route::patch('/brands/{brand}', [CatalogController::class, 'updateBrand'])->name('catalog.brands.update');
        Route::delete('/brands/{brand}', [CatalogController::class, 'destroyBrand'])->name('catalog.brands.destroy');
        Route::post('/units', [CatalogController::class, 'storeUnit'])->name('catalog.units.store');
        Route::patch('/units/{unit}', [CatalogController::class, 'updateUnit'])->name('catalog.units.update');
        Route::delete('/units/{unit}', [CatalogController::class, 'destroyUnit'])->name('catalog.units.destroy');
    });

    // Suppliers (Owner / Admin)
    Route::middleware('permission:suppliers.manage')->prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::patch('/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // Products (all staff can view; mutations gated individually)
    Route::middleware('permission:products.view')->prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('products.index');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
        Route::patch('/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
    });

    // Purchase orders (Owner / Admin)
    Route::middleware('permission:purchase_orders.manage')->prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::patch('/{order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::patch('/{order}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('permission:purchase_orders.approve')->name('purchase-orders.approve');
        Route::post('/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::delete('/{order}', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    });

    // Stock levels (all staff)
    Route::middleware('permission:stock.view')->prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index'])->name('stock.index');
        Route::get('/requests', [RestockRequestController::class, 'index'])
            ->middleware('permission:stock.restock.approve')->name('stock.requests');
        Route::post('/restock-requests', [RestockRequestController::class, 'store'])->name('stock.restock-requests.store');
        Route::patch('/restock-requests/{restockRequest}', [RestockRequestController::class, 'approve'])
            ->middleware('permission:stock.restock.approve')->name('stock.restock-requests.approve');
        Route::patch('/restock-requests/{restockRequest}/reject', [RestockRequestController::class, 'reject'])
            ->middleware('permission:stock.restock.approve')->name('stock.restock-requests.reject');
        Route::get('/transfers', [StockTransferController::class, 'index'])->middleware('permission:stock.transfer')->name('stock-transfers.index');
        Route::get('/transfers/create', [StockTransferController::class, 'create'])->middleware('permission:stock.transfer')->name('stock-transfers.create');
        Route::post('/transfers', [StockTransferController::class, 'store'])->middleware('permission:stock.transfer')->name('stock-transfers.store');
        Route::get('/transfers/{transfer}', [StockTransferController::class, 'show'])->middleware('permission:stock.transfer')->name('stock-transfers.show');
        Route::post('/transfers/{transfer}/ship', [StockTransferController::class, 'ship'])->middleware('permission:stock.transfer')->name('stock-transfers.ship');
        Route::post('/transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:stock.transfer')->name('stock-transfers.receive');
        Route::delete('/transfers/{transfer}', [StockTransferController::class, 'cancel'])->middleware('permission:stock.transfer')->name('stock-transfers.cancel');
    });

    // Stock takes (counters + approvers)
    Route::middleware('permission:stock.take')->prefix('stock-takes')->group(function () {
        Route::get('/', [StockTakeController::class, 'index'])->name('stock-takes.index');
        Route::get('/create', [StockTakeController::class, 'create'])->name('stock-takes.create');
        Route::post('/', [StockTakeController::class, 'commence'])->name('stock-takes.store');
        Route::get('/{take}', [StockTakeController::class, 'show'])->name('stock-takes.show');
        Route::post('/{take}/counts', [StockTakeController::class, 'submitCounts'])->name('stock-takes.counts');
        Route::post('/{take}/approve', [StockTakeController::class, 'approve'])->middleware('permission:stock.take.approve')->name('stock-takes.approve');
        Route::delete('/{take}', [StockTakeController::class, 'cancel'])->name('stock-takes.cancel');
    });

    // Branch ordering workflow (branch orders -> godown picking/dispatch -> receiving)
    Route::middleware('permission:orders.view')->prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/create', [OrderController::class, 'create'])->middleware('permission:orders.create')->name('orders.create');
        Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.create')->name('orders.store');
        Route::get('/rules', [OrderController::class, 'rules'])->name('orders.rules');
        Route::post('/{order}/submit', [OrderController::class, 'submit'])->middleware('permission:orders.create')->name('orders.submit');
        Route::post('/{order}/approve', [OrderController::class, 'approve'])->middleware('permission:orders.approve')->name('orders.approve');
        Route::post('/{order}/reject', [OrderController::class, 'reject'])->middleware('permission:orders.approve')->name('orders.reject');
        Route::post('/{order}/return', [OrderController::class, 'returnForChanges'])->middleware('permission:orders.approve')->name('orders.return');
        Route::post('/{order}/pick/start', [OrderController::class, 'startPicking'])->middleware('permission:godown.picking')->name('orders.pick.start');
        Route::post('/{order}/pick/complete', [OrderController::class, 'completePicking'])->middleware('permission:godown.picking')->name('orders.pick.complete');
        Route::post('/{order}/dispatch', [OrderController::class, 'dispatch'])->middleware('permission:godown.dispatch')->name('orders.dispatch');
        Route::get('/{order}/dispatch-note', [OrderController::class, 'dispatchNote'])->middleware('permission:godown.dispatch')->name('orders.dispatch-note');
        Route::post('/{order}/receive', [OrderController::class, 'receive'])->middleware('permission:orders.receive')->name('orders.receive');
        Route::post('/{order}/resolve', [OrderController::class, 'resolve'])->middleware('permission:orders.review')->name('orders.resolve');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.cancel')->name('orders.cancel');
        Route::get('/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    // POS terminal (all staff with sales access)
    Route::middleware('permission:sales.view')->prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/pos', [SaleController::class, 'pos'])->name('pos.index');
        Route::post('/', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('/{sale}/reprint', [SaleController::class, 'reprint'])->name('sales.reprint');
        Route::post('/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');
        Route::post('/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
        Route::delete('/{sale}', [SaleController::class, 'destroy'])->middleware('permission:sales.delete')->name('sales.destroy');
    });

    // Parked / held sales
    Route::middleware('permission:sales.park')->prefix('parked-sales')->group(function () {
        Route::get('/', [ParkedSaleController::class, 'index'])->name('parked-sales.index');
        Route::post('/', [ParkedSaleController::class, 'store'])->name('parked-sales.store');
        Route::post('/{parked}/recall', [ParkedSaleController::class, 'recall'])->name('parked-sales.recall');
        Route::delete('/{parked}', [ParkedSaleController::class, 'destroy'])->name('parked-sales.destroy');
    });

    // Shifts / cash drawer
    Route::middleware('permission:shift.open')->prefix('shifts')->group(function () {
        Route::get('/', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });

    // Coupons
    Route::middleware('permission:coupons.manage')->prefix('coupons')->group(function () {
        Route::get('/', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('/', [CouponController::class, 'store'])->name('coupons.store');
        Route::patch('/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    });
    Route::post('/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');

    // Customers (CRM as-of Module 5: credit, deposits, loyalty)
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::middleware('permission:customers.view')->prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.manage')->name('customers.store');
        Route::patch('/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');
        Route::post('/{customer}/credit', [CustomerController::class, 'collectCredit'])->middleware('permission:customers.credit')->name('customers.credit');
        Route::post('/{customer}/deposit', [CustomerController::class, 'deposit'])->middleware('permission:customers.manage')->name('customers.deposit');
        Route::post('/{customer}/points', [CustomerController::class, 'adjustPoints'])->middleware('permission:customers.loyalty')->name('customers.points');
    });

    // Loyalty rewards (CRM Module 5)
    Route::middleware('permission:customers.loyalty')->prefix('loyalty-rewards')->group(function () {
        Route::get('/', [LoyaltyRewardController::class, 'index'])->name('loyalty-rewards.index');
        Route::post('/', [LoyaltyRewardController::class, 'store'])->name('loyalty-rewards.store');
        Route::patch('/{reward}', [LoyaltyRewardController::class, 'update'])->name('loyalty-rewards.update');
        Route::delete('/{reward}', [LoyaltyRewardController::class, 'destroy'])->name('loyalty-rewards.destroy');
    });
    Route::get('/loyalty-rewards/options', [LoyaltyRewardController::class, 'options'])->name('loyalty-rewards.options');

    // Layaways
    Route::middleware('permission:sales.layaway')->prefix('layaways')->group(function () {
        Route::get('/', [LayawayController::class, 'index'])->name('layaways.index');
        Route::get('/{layaway}', [LayawayController::class, 'show'])->name('layaways.show');
        Route::post('/{layaway}/pay', [LayawayController::class, 'pay'])->name('layaways.pay');
        Route::post('/{layaway}/cancel', [LayawayController::class, 'cancel'])->name('layaways.cancel');
    });

    // Product lookup for POS (JSON)
    Route::post('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');

    // Finance: reconciliation, drawer, refunds, voids, audit, reports, tax, accounting, policy
    Route::prefix('finance')->group(function () {
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])
            ->middleware('permission:approve shift reconciliation')->name('finance.reconciliation');
        Route::post('/reconciliation/{shift}/approve', [ReconciliationController::class, 'approve'])
            ->middleware('permission:approve shift reconciliation')->name('finance.reconciliation.approve');

        Route::get('/drawer', [CashDrawerController::class, 'index'])
            ->middleware('permission:view cash drawer logs')->name('finance.drawer');
        Route::post('/cash-movements', [CashDrawerController::class, 'store'])->name('finance.cash-movements.store');
        Route::post('/cash-movements/{movement}/approve', [CashDrawerController::class, 'approve'])
            ->middleware('permission:manage petty cash')->name('finance.cash-movements.approve');
        Route::post('/cash-movements/{movement}/reject', [CashDrawerController::class, 'reject'])
            ->middleware('permission:manage petty cash')->name('finance.cash-movements.reject');

        Route::get('/refunds', [RefundApprovalController::class, 'index'])
            ->middleware('permission:approve refunds')->name('finance.refunds');
        Route::post('/refunds/{refundRequest}/approve', [RefundApprovalController::class, 'approve'])
            ->middleware('permission:approve refunds')->name('finance.refunds.approve');
        Route::post('/refunds/{refundRequest}/reject', [RefundApprovalController::class, 'reject'])
            ->middleware('permission:approve refunds')->name('finance.refunds.reject');

        Route::get('/voids', [VoidAuditController::class, 'index'])
            ->middleware('permission:view void logs')->name('finance.voids');

        Route::get('/audit', [AuditTrailController::class, 'index'])
            ->middleware('permission:view audit trails')->name('finance.audit');

        Route::get('/reports', [FinanceReportController::class, 'index'])
            ->middleware('permission:view financial reports')->name('finance.reports');
        Route::get('/reports/export', [FinanceReportController::class, 'export'])
            ->middleware('permission:export sales reports')->name('finance.reports.export');

        Route::get('/tax', [TaxController::class, 'index'])
            ->middleware('permission:manage tax settings')->name('finance.tax');
        Route::post('/tax', [TaxController::class, 'update'])
            ->middleware('permission:manage tax settings')->name('finance.tax.update');

        Route::get('/accounting', [AccountingController::class, 'index'])
            ->middleware('permission:manage accounts receivable')->name('finance.accounting');
        Route::post('/accounting/invoices/{invoice}/pay', [AccountingController::class, 'payInvoice'])
            ->middleware('permission:manage supplier invoices')->name('finance.accounting.invoices.pay');
        Route::get('/accounting/export', [AccountingController::class, 'export'])
            ->middleware('permission:export sales reports')->name('finance.accounting.export');

        Route::get('/policy', [FinancePolicyController::class, 'index'])
            ->middleware('permission:manage discount policy')->name('finance.policy');
        Route::post('/policy', [FinancePolicyController::class, 'update'])
            ->middleware('permission:manage discount policy')->name('finance.policy.update');
    });
});
