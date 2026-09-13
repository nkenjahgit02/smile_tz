<?php

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\ParkedSale;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReceiptCopy;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\StockService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

if (! function_exists('salesOwner')) {
    function salesOwner(): User
    {
        $owner = User::factory()->create([
            'branch_id' => Branch::where('code', 'MAIN')->value('id'),
            'password' => 'password',
        ]);
        $owner->assignRole(['Owner']);

        return $owner;
    }
}

if (! function_exists('restockProduct')) {
    function restockProduct(float $qty = 100, float $cost = 200, float $shop = 500): Product
    {
        $branch = Branch::where('code', 'MAIN')->firstOrFail();
        $product = Product::create([
            'name' => 'Test SKU '.Str::upper(Str::random(4)),
            'sku' => 'SALE-SKU-'.Str::upper(Str::random(4)),
            'godown_price' => $cost * 0.7,
            'shop_price' => $shop,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        app(StockService::class)->restock($branch->id, $product->id, null, $qty, $cost);

        return $product;
    }
}

if (! function_exists('checkoutPayload')) {
    function checkoutPayload(array $items, array $payments, array $extra = []): array
    {
        return array_merge([
            'items' => $items,
            'payments' => $payments,
        ], $extra);
    }
}

it('records a cash sale, consumes FIFO stock and computes profit', function () {
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $product = restockProduct(qty: 50, cost: 200);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 10,
            'discount' => 0,
            'price' => 500,
        ]],
        payments: [['method' => 'cash', 'amount' => 5000]],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect($sale->subtotal)->toEqual(5000.0)
        ->and($sale->total)->toEqual(5000.0)
        ->and($sale->amount_paid)->toEqual(5000.0)
        ->and($sale->payment_status)->toEqual('paid')
        ->and($sale->status)->toEqual('active')
        ->and($sale->items()->count())->toEqual(1);

    // Window of FIFO consumption: 50@200 -> sold 10@200 = 2000 COGS, profit 3000.
    $item = $sale->items()->first();
    expect((float) $item->cost_price)->toEqual(2000.0)
        ->and((float) $item->profit)->toEqual(3000.0)
        ->and((bool) $item->is_loss)->toBeFalse();

    expect(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(40.0);
    expect(Payment::where('sale_id', $sale->id)->where('method', 'cash')->count())->toEqual(1);
    expect(ReceiptCopy::where('sale_id', $sale->id)->count())->toEqual(1);
});

it('applies a percentage coupon and records the discount', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $coupon = Coupon::create([
        'code' => 'SAVE10',
        'type' => 'percentage',
        'value' => 10,
        'min_spend' => 100,
        'max_discount' => 1000,
        'is_active' => true,
    ]);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        payments: [['method' => 'card', 'amount' => 1800]],
        extra: ['coupon_code' => 'SAVE10'],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    // subtotal 2000, coupon 10% = 200 off, total 1800
    expect((float) $sale->subtotal)->toEqual(2000.0)
        ->and((float) $sale->discount_total)->toEqual(200.0)
        ->and((float) $sale->total)->toEqual(1800.0)
        ->and($coupon->fresh()->used_count)->toEqual(1);

    $pivot = $sale->coupons()->where('coupon_id', $coupon->id)->first();
    expect((float) $pivot->pivot->discount_amount)->toEqual(200.0);
});

it('requires a loss reason when selling below cost', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 300, shop: 250);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 250, // below the 300 cost
        ]],
        payments: [['method' => 'cash', 'amount' => 1250]],
    ))->assertSessionHasErrors('items.0.loss_reason');

    expect(Sale::count())->toEqual(0);
});

it('accepts a loss sale when a reason is provided', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 300, shop: 250);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 250,
            'loss_reason' => 'overstock clearance',
        ]],
        payments: [['method' => 'cash', 'amount' => 1250]],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    $item = $sale->items()->first();
    expect((float) $item->profit)->toEqual(-250.0)
        ->and((bool) $item->is_loss)->toBeTrue()
        ->and($item->loss_reason)->toEqual('overstock clearance');
});

it('requires a reason when the amount paid is below the total', function () {
    $seller = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $seller->assignRole(['Seller']);
    $product = restockProduct(qty: 20, cost: 100, shop: 500);

    $this->actingAs($seller)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 400]], // total is 500
    ))->assertSessionHasErrors('price_reduction_reason');

    expect(Sale::count())->toEqual(0);
});

it('accepts a short payment when a discount reason is given and notifies dashboard users with the seller name', function () {
    Notification::fake();
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 500);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 400]], // total is 500
        extra: ['price_reduction_reason' => 'discount'],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((bool) $sale->has_price_reduction)->toBeTrue()
        ->and((float) $sale->price_reduction_amount)->toEqual(100.0)
        ->and($sale->price_reduction_reason)->toEqual('discount')
        ->and((float) $sale->total)->toEqual(500.0)
        ->and((float) $sale->amount_paid)->toEqual(400.0)
        ->and($sale->payment_status)->toEqual('partial');

    Notification::assertSentTo(
        $owner,
        SystemNotification::class,
        fn (SystemNotification $n) => str_contains($n->message, $owner->name),
    );

    $snapshot = ReceiptCopy::where('sale_id', $sale->id)->firstOrFail()->content_snapshot;
    expect((bool) ($snapshot['has_price_reduction']))->toBeTrue()
        ->and((float) $snapshot['price_reduction_amount'])->toEqual(100.0)
        ->and($snapshot['price_reduction_reason'])->toEqual('discount');
});

it('accepts overpayment (change) when an overpayment reason is given', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 500);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 2]],
        payments: [['method' => 'cash', 'amount' => 1200]], // total is 1000
        extra: ['price_reduction_reason' => 'overpayment'],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) $sale->price_reduction_amount)->toEqual(200.0)
        ->and($sale->price_reduction_reason)->toEqual('overpayment')
        ->and((float) $sale->change_amount)->toEqual(200.0)
        ->and((bool) $sale->has_price_reduction)->toBeTrue();
});

it('completes without a reason when the payment equals the total', function () {
    $seller = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $seller->assignRole(['Seller']);
    $product = restockProduct(qty: 20, cost: 100, shop: 500);

    $this->actingAs($seller)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 2]],
        payments: [['method' => 'cash', 'amount' => 1000]],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((bool) $sale->has_price_reduction)->toBeFalse();
});

it('supports split payments (cash + card)', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        payments: [
            ['method' => 'cash', 'amount' => 500],
            ['method' => 'card', 'amount' => 1500],
        ],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect(Payment::where('sale_id', $sale->id)->count())->toEqual(2)
        ->and((float) $sale->total)->toEqual(2000.0)
        ->and((float) $sale->amount_paid)->toEqual(2000.0);
});

it('calculates change from cash tendered', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 200,
        ]],
        payments: [['method' => 'cash', 'amount' => 500]],
        extra: ['price_reduction_reason' => 'overpayment'],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) $sale->change_amount)->toEqual(100.0)
        ->and((float) $sale->amount_paid)->toEqual(400.0);
});

it('blocks a price increase (markup) for a user without the permission', function () {
    $seller = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $seller->assignRole(['Seller']);

    $product = restockProduct(qty: 20, cost: 100);

    $this->actingAs($seller)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 900, // above the 500 catalog price; markup is not allowed
        ]],
        payments: [['method' => 'cash', 'amount' => 900]],
    ))->assertSessionHasErrors('items.0.price');

    expect(Sale::count())->toEqual(0);
});

it('allows layaway: deposits and tracks installments', function () {
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $customer = Customer::create([
        'branch_id' => $branch->id,
        'customer_no' => 'CUST-TEST-1',
        'name' => 'Jane Doe',
        'customer_type' => 'member',
    ]);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 200,
        ]],
        payments: [['method' => 'cash', 'amount' => 1000]],
        extra: ['customer_id' => $customer->id, 'layaway_deposit' => 1000],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect((float) $sale->amount_paid)->toEqual(1000.0)
        ->and($sale->payment_status)->toEqual('partial');

    $layaway = $customer->layaways()->first();
    expect($layaway)->not->toBeNull()
        ->and((float) $layaway->balance)->toEqual(1000.0)
        ->and((float) $layaway->paid_amount)->toEqual(1000.0)
        ->and($layaway->status)->toEqual('active');

    // Second installment of 400 + final 600.
    $this->actingAs($owner)->post(route('layaways.pay', $layaway), [
        'amount' => 400,
        'method' => 'card',
    ])->assertSessionHas('success');

    $this->actingAs($owner)->post(route('layaways.pay', $layaway), [
        'amount' => 600,
        'method' => 'cash',
    ])->assertSessionHas('success');

    expect($layaway->fresh()->status)->toEqual('fulfilled')
        ->and((float) $layaway->fresh()->balance)->toEqual(0.0);
});

it('opens and closes a shift with drawer reconciliation', function () {
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $product = restockProduct(qty: 20, cost: 100, shop: 1000);

    // Open shift with float 50k.
    $this->actingAs($owner)->post('/shifts', ['opening_float' => 50000])
        ->assertRedirect(route('pos.index'));

    $shift = Shift::open()->where('branch_id', $branch->id)->first();
    expect($shift)->not->toBeNull();

    // Make a 2000 cash sale during the shift.
    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000,
        ]],
        payments: [['method' => 'cash', 'amount' => 2000]],
    ))->assertRedirect();

    // Drawer expected: 50k float + 2k cash = 52k. Count handed in 52k.
    $this->actingAs($owner)->post(route('shifts.close', $shift), ['closing_count' => 52000])
        ->assertRedirect(route('shifts.index'));

    $closed = $shift->fresh();
    expect($closed->status)->toEqual('closed')
        ->and((float) $closed->expected_in_drawer)->toEqual(52000.0)
        ->and((float) $closed->difference)->toEqual(0.0);
});

it('rejects an under-collected payment', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 200,
        ]],
        payments: [['method' => 'cash', 'amount' => 500]], // need 1000
    ))->assertSessionHasErrors('price_reduction_reason');

    expect(Sale::count())->toEqual(0);
});

it('parks and recalls a sale', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 20, cost: 100, shop: 200);

    $this->actingAs($owner)->post('/parked-sales', [
        'reference' => 'Walk-in table 3',
        'cart' => [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 3,
                'price' => 200,
            ]],
        ],
    ])->assertRedirect();

    $parked = ParkedSale::firstOrFail();
    expect($parked->reference)->toEqual('Walk-in table 3')
        ->and($parked->cart_json['items'][0]['product_id'])->toEqual($product->id);

    $this->actingAs($owner)->post(route('parked-sales.recall', $parked))
        ->assertJson(['id' => $parked->id])
        ->assertJsonPath('items.0.product_id', $product->id)
        ->assertJsonPath('items.0.name', $product->name)
        ->assertJsonPath('items.0.quantity', 3)
        ->assertJsonPath('items.0.unit_price', 200)
        ->assertJsonPath('items.0.catalog_price', 200);

    expect($parked->fresh()->recalled_at)->not->toBeNull();
});

it('voids a sale and restores stock', function () {
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $product = restockProduct(qty: 50, cost: 200);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 500,
        ]],
        payments: [['method' => 'cash', 'amount' => 5000]],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(40.0);

    $this->actingAs($owner)->post(route('sales.void', $sale), ['reason' => 'customer changed mind'])
        ->assertSessionHas('success');

    expect($sale->fresh()->status)->toEqual('voided')
        ->and(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(50.0);
});
