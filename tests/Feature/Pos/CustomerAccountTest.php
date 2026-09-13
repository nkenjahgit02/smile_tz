<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\LoyaltyReward;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\CustomerAccountService;
use App\Support\StockService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

if (! function_exists('crmOwner')) {
    function crmOwner(): User
    {
        $owner = User::factory()->create([
            'branch_id' => Branch::where('code', 'MAIN')->value('id'),
            'password' => 'password',
        ]);
        $owner->assignRole(['Owner']);

        return $owner;
    }
}

if (! function_exists('makeCustomer')) {
    function makeCustomer(string $type = 'member'): Customer
    {
        return Customer::create([
            'branch_id' => Branch::where('code', 'MAIN')->value('id'),
            'customer_no' => 'CUST-CRM-'.Str::upper(Str::random(4)),
            'name' => 'CRM Test '.Str::upper(Str::random(3)),
            'customer_type' => $type,
            'credit_limit' => 0,
        ]);
    }
}

if (! function_exists('crmProduct')) {
    function crmProduct(float $qty = 20, float $cost = 100, float $shop = 200): Product
    {
        $branch = Branch::where('code', 'MAIN')->firstOrFail();
        $product = Product::create([
            'name' => 'CRM SKU '.Str::upper(Str::random(4)),
            'sku' => 'CRM-SKU-'.Str::upper(Str::random(4)),
            'godown_price' => $cost * 0.7,
            'shop_price' => $shop,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        app(StockService::class)->restock($branch->id, $product->id, null, $qty, $cost);

        return $product;
    }
}

it('extends credit on a credit sale and records the AR ledger', function () {
    $owner = crmOwner();
    $customer = makeCustomer('credit');
    $customer->update(['credit_limit' => 5000]);
    $product = crmProduct();

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        'payments' => [['method' => 'credit', 'amount' => 2000]],
        'customer_id' => $customer->id,
    ])->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) $sale->amount_paid)->toEqual(2000.0)
        ->and($sale->payment_status)->toEqual('paid')
        ->and(Payment::where('sale_id', $sale->id)->where('method', 'credit')->count())->toEqual(1);

    $customer->refresh();
    expect((float) $customer->credit_balance)->toEqual(2000.0);

    $ledger = $customer->transactions()->where('type', 'credit_sale')->first();
    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->running_credit)->toEqual(2000.0)
        ->and($ledger->reference_type)->toEqual('sale')
        ->and($ledger->reference_id)->toEqual($sale->id);
});

it('rejects a credit payment that exceeds the customer credit limit', function () {
    $owner = crmOwner();
    $customer = makeCustomer('credit');
    $customer->update(['credit_limit' => 1000]);
    $product = crmProduct();

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        'payments' => [['method' => 'credit', 'amount' => 2000]],
        'customer_id' => $customer->id,
    ])->assertSessionHasErrors('payments');

    expect(Sale::count())->toEqual(0)
        ->and($customer->fresh()->credit_balance)->toEqual(0.0);
});

it('rejects credit payment for a non-credit customer', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct();

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 200,
        ]],
        'payments' => [['method' => 'credit', 'amount' => 200]],
        'customer_id' => $customer->id,
    ])->assertSessionHasErrors('payments');

    expect(Sale::count())->toEqual(0);
});

it('requires a customer to pay via deposit or credit', function () {
    $owner = crmOwner();
    $product = crmProduct();

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 200,
        ]],
        'payments' => [['method' => 'deposit', 'amount' => 200]],
    ])->assertSessionHasErrors('payments');

    expect(Sale::count())->toEqual(0);
});

it('records a deposit and spends from it on a sale', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct();

    $this->actingAs($owner)->post(route('customers.deposit', $customer), [
        'amount' => 2500,
        'notes' => 'wallet top-up',
    ])->assertSessionHas('success');

    expect((float) $customer->fresh()->deposit_balance)->toEqual(2500.0);
    $depositLedger = $customer->transactions()->where('type', 'deposit')->latest()->first();
    expect($depositLedger)->not->toBeNull()
        ->and((float) $depositLedger->running_deposit)->toEqual(2500.0);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        'payments' => [['method' => 'deposit', 'amount' => 2000]],
        'customer_id' => $customer->id,
    ])->assertRedirect();

    expect((float) $customer->fresh()->deposit_balance)->toEqual(500.0);

    $spend = $customer->transactions()->where('type', 'deposit_reversal')->latest()->first();
    expect($spend)->not->toBeNull()
        ->and((float) $spend->amount)->toEqual(-2000.0)
        ->and((float) $spend->running_deposit)->toEqual(500.0);
});

it('rejects a deposit payment larger than the customer balance', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct();

    $this->actingAs($owner)->post(route('customers.deposit', $customer), [
        'amount' => 500,
    ])->assertSessionHas('success');

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        'payments' => [['method' => 'deposit', 'amount' => 2000]],
        'customer_id' => $customer->id,
    ])->assertSessionHasErrors('payments');

    expect(Sale::count())->toEqual(0)
        ->and((float) $customer->fresh()->deposit_balance)->toEqual(500.0);
});

it('earns loyalty points on a paid sale and records the earn', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct(qty: 50, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 5000]],
        'customer_id' => $customer->id,
    ])->assertRedirect();

    $customer->refresh();
    expect($customer->loyalty_points)->toEqual(5)
        ->and($customer->lifetime_points)->toEqual(5)
        ->and($customer->loyalty_tier)->toEqual('bronze');

    $earn = $customer->transactions()->where('type', 'loyalty_earn')->first();
    expect($earn)->not->toBeNull()
        ->and($earn->points)->toEqual(5)
        ->and($earn->reference_type)->toEqual('sale');
});

it('redeems a loyalty reward during checkout and applies its discount', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct();

    app(CustomerAccountService::class)->adjustPoints($customer, 500, $customer->branch_id, 'test bonus', $owner);
    $customer->refresh();
    expect($customer->loyalty_points)->toEqual(500)
        ->and($customer->loyalty_tier)->toEqual('silver');

    $reward = LoyaltyReward::create([
        'name' => '10% Off',
        'points_cost' => 200,
        'type' => 'percentage',
        'value' => 10,
        'min_tier' => 'silver',
        'is_active' => true,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        'payments' => [['method' => 'card', 'amount' => 1800]],
        'customer_id' => $customer->id,
        'reward_id' => $reward->id,
    ])->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) $sale->subtotal)->toEqual(2000.0)
        ->and((float) $sale->discount_total)->toEqual(200.0)
        ->and((float) $sale->total)->toEqual(1800.0);

    // 500 - 200 (reward) + 1 (earn on 1800 paid) = 301
    $customer->refresh();
    expect($customer->loyalty_points)->toEqual(301)
        ->and($customer->lifetime_points)->toEqual(501)
        ->and($customer->loyalty_tier)->toEqual('silver');

    $redeem = $customer->transactions()->where('type', 'loyalty_redeem')->first();
    expect($redeem)->not->toBeNull()
        ->and($redeem->points)->toEqual(-200)
        ->and($redeem->reference_type)->toEqual('loyalty_reward')
        ->and($redeem->reference_id)->toEqual($reward->id);
});

it('rejects a reward redemption when the customer lacks points', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');
    $product = crmProduct();

    $reward = LoyaltyReward::create([
        'name' => '1000 Off',
        'points_cost' => 200,
        'type' => 'fixed',
        'value' => 50,
        'min_tier' => 'bronze',
        'is_active' => true,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 200,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 200]],
        'customer_id' => $customer->id,
        'reward_id' => $reward->id,
    ])->assertSessionHasErrors('reward');

    expect(Sale::count())->toEqual(0)
        ->and($customer->fresh()->loyalty_points)->toEqual(0);
});

it('collects a credit payment and updates the running balances', function () {
    $owner = crmOwner();
    $customer = makeCustomer('credit');
    $customer->update(['credit_limit' => 5000, 'credit_balance' => 2000]);
    $customer->transactions()->create([
        'branch_id' => $customer->branch_id,
        'type' => 'credit_sale',
        'amount' => 2000,
        'running_credit' => 2000,
        'running_deposit' => 0,
        'running_points' => 0,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)->post(route('customers.credit', $customer), [
        'amount' => 1500,
        'method' => 'cash',
    ])->assertSessionHas('success');

    expect((float) $customer->fresh()->credit_balance)->toEqual(500.0);

    $payment = $customer->transactions()->where('type', 'credit_payment')->latest()->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toEqual(-1500.0)
        ->and((float) $payment->running_credit)->toEqual(500.0);
});

it('prevents collecting more credit than is outstanding', function () {
    $owner = crmOwner();
    $customer = makeCustomer('credit');
    $customer->update(['credit_limit' => 1000, 'credit_balance' => 300]);

    $this->actingAs($owner)->post(route('customers.credit', $customer), [
        'amount' => 500,
        'method' => 'cash',
    ])->assertSessionHasErrors('credit');

    expect((float) $customer->fresh()->credit_balance)->toEqual(300.0);
    expect(CustomerTransaction::where('type', 'credit_payment')->count())->toEqual(0);
});

it('adjusts loyalty points and recomputes the tier', function () {
    $owner = crmOwner();
    $customer = makeCustomer('member');

    $this->actingAs($owner)->post(route('customers.points', $customer), [
        'points' => 1000,
        'reason' => 'promo bonus',
    ])->assertSessionHas('success');

    expect($customer->fresh()->loyalty_points)->toEqual(1000)
        ->and($customer->fresh()->lifetime_points)->toEqual(1000)
        ->and($customer->fresh()->loyalty_tier)->toEqual('gold');
});

it('upgrades the tier via the points thresholds', function () {
    $customer = makeCustomer('member');

    $service = app(CustomerAccountService::class);
    expect($service->tierFor(0, 0))->toEqual('bronze')
        ->and($service->tierFor(500, 0))->toEqual('silver')
        ->and($service->tierFor(1000, 0))->toEqual('gold')
        ->and($service->tierFor(2000, 0))->toEqual('platinum');
});
