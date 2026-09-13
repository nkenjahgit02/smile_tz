<?php

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\StockService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
});

if (! function_exists('salesOwner')) {
    function salesOwner(): User
    {
        $owner = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
        $owner->assignRole(['Owner']);

        return $owner;
    }
}

if (! function_exists('restockProduct')) {
    function restockProduct(float $qty = 100, float $cost = 200, float $shop = 500): Product
    {
        $branch = Branch::where('code', 'MAIN')->firstOrFail();
        $product = Product::create([
            'name' => 'Finance SKU '.Str::upper(Str::random(4)),
            'sku' => 'FIN-SKU-'.Str::upper(Str::random(4)),
            'godown_price' => $cost * 0.7,
            'shop_price' => $shop,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        app(StockService::class)->restock($branch->id, $product->id, null, $qty, $cost);

        return $product;
    }
}

function financeUser(): User
{
    $user = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
    $user->assignRole(['Finance']);

    return $user;
}

function skillsSeller(): User
{
    $seller = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
    $seller->assignRole(['Seller']);

    return $seller;
}

it('keeps the finance role read-only at the point of sale', function () {
    $finance = financeUser();
    $owner = salesOwner();
    $product = restockProduct();

    // No sales creation (sales.create is withheld from Finance).
    $this->actingAs($finance)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertForbidden();

    // No product price edits / deletions.
    $this->actingAs($finance)->patch(route('products.update', $product), ['shop_price' => 999])->assertForbidden();
    $this->actingAs($finance)->delete(route('products.destroy', $product))->assertForbidden();

    // A cashier creates a sale first.
    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertRedirect();

    $sale = Sale::firstOrFail();

    // Separation of duties: Finance never initiates refunds or voids.
    $this->actingAs($finance)->post(route('sales.refund', $sale), [
        'amount' => 100,
        'reason' => 'tamper',
    ])->assertForbidden();
    $this->actingAs($finance)->post(route('sales.void', $sale), ['reason' => 'tamper'])->assertForbidden();

    // No stock adjustments.
    $this->actingAs($finance)->get('/stock')->assertForbidden();

    expect($sale->fresh()->status)->toEqual('active');
});

it('auto-approves small refunds and executes them immediately', function () {
    $seller = skillsSeller();
    $product = restockProduct(qty: 50, cost: 100, shop: 500);

    $this->actingAs($seller)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) Stock::forLocation(Branch::where('code', 'MAIN')->value('id'), $product->id)->quantity)->toEqual(48.0);

    // Refund below the 50,000 auto-approve limit.
    $this->actingAs($seller)->post(route('sales.refund', $sale), [
        'amount' => 500,
        'reason' => 'damaged item',
        'restock' => 1,
    ])->assertSessionHas('success');

    $request = RefundRequest::firstOrFail();
    expect($request->status)->toEqual('auto_approved')
        ->and($sale->fresh()->status)->toEqual('returned')
        ->and((float) Stock::forLocation(Branch::where('code', 'MAIN')->value('id'), $product->id)->quantity)->toEqual(50.0);
});

it('queues refunds above the auto-approve limit and notifies finance, then approves on review', function () {
    $finance = financeUser();
    $seller = skillsSeller();
    $product = restockProduct(qty: 200, cost: 100, shop: 500);

    $this->actingAs($seller)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 200,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 100000]],
    ])->assertRedirect();

    $sale = Sale::firstOrFail();

    // Refund above the 50,000 limit -> pending for Finance.
    $this->actingAs($seller)->post(route('sales.refund', $sale), [
        'amount' => 70000,
        'reason' => 'customer returned the order',
    ])->assertSessionHas('success');

    $request = RefundRequest::firstOrFail();
    expect($request->status)->toEqual('pending')
        ->and($sale->fresh()->status)->toEqual('active');

    // Finance received a refund_pending notification.
    $notification = $finance->notifications()->where('category', 'finance')->first();
    expect($notification)->not->toBeNull()
        ->and($notification->finance_type)->toEqual('refund_pending')
        ->and($notification->data['link'])->toEqual('/finance/refunds');

    // Same sale cannot be refunded twice while pending + finance listed it.
    $this->actingAs($finance)->get('/finance/refunds')
        ->assertOk();

    // Finance approves -> the refund is executed.
    $this->actingAs($finance)->post(route('finance.refunds.approve', $request))
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toEqual('approved')
        ->and($sale->fresh()->status)->toEqual('returned');

    // Finance reject flow on a second request (sale already returned -> blocked).
    $this->actingAs($seller)->post(route('sales.refund', $sale), [
        'amount' => 500,
        'reason' => 'late complaint',
    ])->assertForbidden();

    // No duplicate refund notification was pushed by the blocked attempt.
    expect($finance->notifications()->where('finance_type', 'refund_pending')->count())->toBe(1);
});

it('enforces the backend discount policy cap for cashiers without price override', function () {
    $seller = skillsSeller();
    $product = restockProduct(qty: 20, cost: 100, shop: 1000);

    $this->actingAs($seller)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
            'discount' => 500, // 50% > the 5% policy cap
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
        'price_reduction_reason' => 'discount',
        'price_reduction_amount' => 500,
    ])->assertSessionHasErrors('items');

    expect(Sale::count())->toEqual(0);

    // A within-policy discount (5% of the discounted subtotal) passes.
    $this->actingAs($seller)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
            'discount' => 45,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 955]],
        'price_reduction_reason' => 'discount',
        'price_reduction_amount' => 45,
    ])->assertRedirect();

    expect(Sale::count())->toEqual(1);
});

it('flags high-value voids and notifies finance', function () {
    $finance = financeUser();
    $owner = salesOwner();
    $product = restockProduct(qty: 500, cost: 100, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 500,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 250000]],
    ])->assertRedirect();

    $sale = Sale::firstOrFail();

    $this->actingAs($owner)->post(route('sales.void', $sale), ['reason' => 'accident'])
        ->assertSessionHas('success');

    $sale = $sale->fresh();
    expect($sale->status)->toEqual('voided')
        ->and($sale->is_void_flagged)->toBeTrue()
        ->and($sale->voided_at)->not->toBeNull();

    $notification = $finance->notifications()->where('finance_type', 'void_flagged')->first();
    expect($notification)->not->toBeNull()
        ->and($notification->data['link'])->toEqual('/finance/voids');

    // Visible on the void audit screen.
    $this->actingAs($finance)->get('/finance/voids')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voids.total', 1)
            ->where('voids.data.0.is_void_flagged', true));
});

it('flags repeated same-day voids after the daily-count threshold', function () {
    $finance = financeUser();
    $owner = salesOwner();
    $product = restockProduct(qty: 50, cost: 100, shop: 500);

    for ($i = 1; $i <= 3; $i++) {
        $this->actingAs($owner)->post('/sales', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 500,
            ]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ])->assertRedirect();

        $sale = Sale::latest('id')->first();
        $this->actingAs($owner)->post(route('sales.void', $sale), ['reason' => 'no reason'])->assertSessionHas('success');
    }

    $flagged = Sale::where('is_void_flagged', true)->count();
    expect($flagged)->toBe(1) // the count-based rule, not the amount rule
        ->and($finance->notifications()->where('finance_type', 'void_flagged')->count())->toBe(1);
});

it('notifies finance of a cash drawer variance and lets them reconcile the shift', function () {
    $finance = financeUser();
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $this->actingAs($owner)->post('/shifts', ['opening_float' => 50000])->assertRedirect();
    $shift = Shift::open()->where('branch_id', $branch->id)->firstOrFail();

    // Count off by +50,000 vs the drawer expectation (threshold 5,000).
    $this->actingAs($owner)->post(route('shifts.close', $shift), ['closing_count' => 100000])
        ->assertRedirect(route('shifts.index'));

    $closed = $shift->fresh();
    expect($closed->status)->toEqual('closed')
        ->and((float) $closed->difference)->toEqual(50000.0);

    $notification = $finance->notifications()->where('finance_type', 'shift_variance')->first();
    expect($notification)->not->toBeNull()
        ->and($notification->data['link'])->toEqual('/finance/reconciliation');

    // Finance sees it on the reconciliation screen and signs it off.
    $this->actingAs($finance)->get('/finance/reconciliation')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('shifts.total', 1));

    $this->actingAs($finance)->post(route('finance.reconciliation.approve', $closed))
        ->assertSessionHas('success');

    expect($closed->fresh()->status)->toEqual('reconciled')
        ->and($closed->fresh()->reviewed_by)->toEqual($finance->id)
        ->and($closed->fresh()->reviewed_at)->not->toBeNull()
        ->and(Activity::where('event', 'shift_reconciled')->count())->toBe(1);
});

it('routes cash-ins to the drawer and cash-outs through finance approval', function () {
    $finance = financeUser();
    $seller = skillsSeller();

    // A cash-in by the cashier lands immediately on the drawer.
    $this->actingAs($seller)->post('/finance/cash-movements', [
        'type' => 'cash_in',
        'amount' => 10000,
    ])->assertSessionHas('success');

    $movement = CashMovement::firstOrFail();
    expect($movement->status)->toEqual('approved');

    // A cash-out goes to pending and must be approved by finance.
    $this->actingAs($seller)->post('/finance/cash-movements', [
        'type' => 'cash_out',
        'amount' => 5000,
        'notes' => 'float top-up',
    ])->assertSessionHas('success');

    $out = CashMovement::latest('id')->firstOrFail();
    expect($out->status)->toEqual('pending');

    $this->actingAs($finance)->post(route('finance.cash-movements.approve', $out))->assertSessionHas('success');
    expect($out->fresh()->status)->toEqual('approved')
        ->and($out->fresh()->approved_by)->toEqual($finance->id);
});

it('gates every finance screen and action behind the exact permissions', function () {
    $finance = financeUser();
    $seller = skillsSeller();

    foreach (['/finance/reconciliation', '/finance/drawer', '/finance/refunds', '/finance/voids', '/finance/audit', '/finance/reports', '/finance/tax', '/finance/accounting', '/finance/policy'] as $url) {
        $this->actingAs($seller)->get($url)->assertForbidden();
        $this->actingAs($finance)->get($url)->assertOk();
    }

    // Approval/administration actions are finance-only.
    $shift = Shift::create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'user_id' => $seller->id,
        'shift_no' => 'SHF-'.Str::upper(Str::random(6)),
        'opening_float' => 0,
        'status' => 'closed',
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
        'expected_in_drawer' => 0,
        'difference' => 0,
    ]);
    $this->actingAs($seller)->post(route('finance.reconciliation.approve', $shift))->assertForbidden();
});

it('records overdue receivables into finance notifications exactly once', function () {
    $finance = financeUser();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $customer = Customer::create([
        'branch_id' => $branch->id,
        'customer_no' => 'CUST-FIN-'.Str::upper(Str::random(4)),
        'name' => 'Overdue Credit Customer',
        'customer_type' => 'credit',
        'credit_balance' => 45000,
        'credit_limit' => 100000,
    ]);

    $tx = $customer->transactions()->create([
        'branch_id' => $branch->id,
        'type' => 'credit_sale',
        'amount' => 45000,
        'reference_type' => 'sale',
        'created_by' => $finance->id,
    ]);
    // created_at is not fillable on the ledger, so backdate after the insert.
    $tx->forceFill(['created_at' => now()->subDays(45)])->save();

    // Two subsequent visits must not produce duplicate notifications.
    $this->actingAs($finance)->get('/finance/accounting')->assertOk();
    $this->actingAs($finance)->get('/finance/accounting')->assertOk();

    $notifications = $finance->notifications()
        ->where('finance_type', 'receivable_overdue')
        ->count();

    expect($notifications)->toBe(1)
        ->and($finance->notifications()->where('finance_type', 'receivable_overdue')->first()->data['link'])
        ->toEqual('/finance/accounting?entity=AR-'.$customer->id);
});

it('records invoice payments against supplier invoices', function () {
    $finance = financeUser();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $invoice = SupplierInvoice::create([
        'branch_id' => $branch->id,
        'supplier_id' => Supplier::create(['name' => 'Acme Supplies'])->id,
        'invoice_no' => 'INV-'.Str::upper(Str::random(6)),
        'amount' => 100000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(10),
        'status' => 'unpaid',
    ]);

    $this->actingAs($finance)->post(route('finance.accounting.invoices.pay', $invoice), [
        'amount' => 40000,
    ])->assertSessionHas('success');

    $invoice = $invoice->fresh();
    expect((float) $invoice->paid_amount)->toEqual(40000.0)
        ->and($invoice->status)->toEqual('partial');

    $this->actingAs($finance)->post(route('finance.accounting.invoices.pay', $invoice), [
        'amount' => 60000,
    ])->assertSessionHas('success');

    expect($invoice->fresh()->status)->toEqual('paid');
});
