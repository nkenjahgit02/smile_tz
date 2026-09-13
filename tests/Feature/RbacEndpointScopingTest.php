<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\StockService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('scopes a seller\'s sales list to sales made at their own location', function () {
    $main = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create(['name' => 'KAR Store', 'code' => 'KAR', 'type' => 'store', 'parent_id' => $main->id, 'currency' => 'TZS']);
    $product = Product::create([
        'name' => 'Scoped SKU '.Str::upper(Str::random(4)),
        'sku' => 'SCOPE-'.Str::upper(Str::random(4)),
        'godown_price' => 700,
        'shop_price' => 1000,
        'reorder_level' => 5,
        'is_active' => true,
    ]);

    $owner = User::factory()->create(['branch_id' => $main->id]);
    $owner->assignRole(['Owner']);
    $seller = User::factory()->create(['branch_id' => $store->id]);
    $seller->assignRole(['Seller']);

    app(StockService::class)->restock($main->id, $product->id, null, 50, 700);
    app(StockService::class)->restock($store->id, $product->id, null, 50, 700);

    // owner sells at MAIN
    $this->actingAs($owner)->post('/sales', [
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertRedirect();

    // seller sells at KAR
    $this->actingAs($seller)->post('/sales', [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1000]],
        'payments' => [['method' => 'cash', 'amount' => 2000]],
    ])->assertRedirect();

    $ownerSale = Sale::where('user_id', $owner->id)->firstOrFail();

    // seller's list only contains their own sale
    $this->actingAs($seller)->get('/sales')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sales.total', 1)
            ->where('sales.data.0.user.id', $seller->id));

    // cross-location detail view is forbidden for the seller
    $this->actingAs($seller)->get("/sales/{$ownerSale->id}")->assertForbidden();
});

it('keeps cross-location sales visible to all-locations roles', function () {
    $main = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create(['name' => 'KAR Store', 'code' => 'KAR', 'type' => 'store', 'parent_id' => $main->id, 'currency' => 'TZS']);
    $product = Product::create([
        'name' => 'Scoped SKU '.Str::upper(Str::random(4)),
        'sku' => 'SCOPE-'.Str::upper(Str::random(4)),
        'godown_price' => 700,
        'shop_price' => 1000,
        'reorder_level' => 5,
        'is_active' => true,
    ]);

    $owner = User::factory()->create(['branch_id' => $main->id]);
    $owner->assignRole(['Owner']);
    $seller = User::factory()->create(['branch_id' => $store->id]);
    $seller->assignRole(['Seller']);

    app(StockService::class)->restock($main->id, $product->id, null, 50, 700);
    app(StockService::class)->restock($store->id, $product->id, null, 50, 700);

    $this->actingAs($seller)->post('/sales', [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1000]],
        'payments' => [['method' => 'cash', 'amount' => 2000]],
    ])->assertRedirect();

    $storeSale = Sale::where('user_id', $seller->id)->firstOrFail();

    // admin and finance can view a sale from another location
    $admin = User::factory()->create(['branch_id' => $main->id]);
    $admin->assignRole(['Admin']);
    $this->actingAs($admin)->get("/sales/{$storeSale->id}")->assertOk();

    $finance = User::factory()->create(['branch_id' => $main->id]);
    $finance->assignRole(['Finance']);
    $this->actingAs($finance)->get("/sales/{$storeSale->id}")->assertOk();
});

it('scopes the stock index to the user\'s locations and rejects branch_id tampering', function () {
    $main = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create(['name' => 'KAR Store', 'code' => 'KAR', 'type' => 'store', 'parent_id' => $main->id, 'currency' => 'TZS']);
    $product = Product::create([
        'name' => 'Scoped SKU '.Str::upper(Str::random(4)),
        'sku' => 'SCOPE-'.Str::upper(Str::random(4)),
        'godown_price' => 70,
        'shop_price' => 100,
        'reorder_level' => 5,
        'is_active' => true,
    ]);

    app(StockService::class)->restock($main->id, $product->id, null, 50, 70);

    $seller = User::factory()->create(['branch_id' => $store->id]);
    $seller->assignRole(['Seller']);

    // requesting the godown branch_id must not leak godown stock into a store seller's listing
    $this->actingAs($seller)->get('/stock?branch_id=MAIN')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stock.total', 0)
            ->where('branches', function ($branches) use ($store) {
                $ids = collect($branches)->pluck('id')->all();

                return in_array($store->id, $ids, true)
                    && ! in_array(Branch::where('code', 'MAIN')->value('id'), $ids, true);
            }));
});

it('forbids finance from stock and activity endpoints entirely', function () {
    $finance = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
    $finance->assignRole(['Finance']);

    $this->actingAs($finance)->get('/stock')->assertForbidden();
    $this->actingAs($finance)->get('/activity')->assertForbidden();
});

it('scopes a seller\'s customer list and forbids foreign-branch customer access', function () {
    $main = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create(['name' => 'KAR Store', 'code' => 'KAR', 'type' => 'store', 'parent_id' => $main->id, 'currency' => 'TZS']);

    $localCustomer = Customer::create([
        'branch_id' => $store->id,
        'customer_no' => 'CUST-LOCAL',
        'name' => 'Local Buyer',
        'phone' => '0700000000',
        'customer_type' => 'walk-in',
    ]);
    Customer::create([
        'branch_id' => $main->id,
        'customer_no' => 'CUST-FOREIGN',
        'name' => 'Godown Buyer',
        'phone' => '0700111111',
        'customer_type' => 'walk-in',
    ]);

    $seller = User::factory()->create(['branch_id' => $store->id]);
    $seller->assignRole(['Seller']);

    $this->actingAs($seller)->get('/customers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $localCustomer->id));

    $this->actingAs($seller)->get("/customers/{$localCustomer->id}")->assertOk();
    $foreign = Customer::where('customer_no', 'CUST-FOREIGN')->firstOrFail();
    $this->actingAs($seller)->get("/customers/{$foreign->id}")->assertForbidden();
});
