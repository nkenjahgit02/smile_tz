<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\CategoryThreshold;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\Stock;
use App\Models\User;
use App\Support\StockService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
            'name' => 'Dash SKU '.Str::upper(Str::random(4)),
            'sku' => 'DASH-SKU-'.Str::upper(Str::random(4)),
            'godown_price' => $cost * 0.7,
            'shop_price' => $shop,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        app(StockService::class)->restock($branch->id, $product->id, null, $qty, $cost);

        return $product;
    }
}

it('redirects guests away from the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows today revenue, transactions, items and basket to the owner', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 50, cost: 200, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 5000]],
    ])->assertRedirect();

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('kpis.revenue.value', fn ($v) => (float) $v === 5000.0)
            ->where('kpis.transactions.value', fn ($v) => (int) $v === 1)
            ->where('kpis.items_sold.value', fn ($v) => (float) $v === 10.0)
            ->where('kpis.avg_basket.value', fn ($v) => (float) $v === 5000.0)
            ->where('refunds.count', fn ($v) => (int) $v === 0)
            ->where('promotions.coupons_used', fn ($v) => (int) $v === 0)
            ->where('payment_split.0.method', 'cash')
            ->where('payment_split.0.amount', fn ($v) => (float) $v === 5000.0)
            ->where('can.sales', true)
            ->where('can.stock', true)
            ->where('can.customers', true)
            ->where('can.activity', true));
});

it('scopes metrics to the dashboard users branch', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 50, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertRedirect();

    $other = Branch::create(['name' => 'Branch Two', 'code' => 'B2', 'currency' => 'TZS']);
    $seller = User::factory()->create(['branch_id' => $other->id]);
    $seller->assignRole(['Seller']);

    $this->actingAs($seller)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.revenue.value', fn ($v) => (float) $v === 0.0)
            ->where('kpis.transactions.value', fn ($v) => (int) $v === 0));

    $this->actingAs($owner)->get('/dashboard?branch='.$other->id)
        ->assertInertia(fn (Assert $page) => $page->where('kpis.revenue.value', fn ($v) => (float) $v === 0.0));

    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

it('sends tiered stock alerts with locations and types', function () {
    $owner = salesOwner();
    restockProduct(qty: 50, shop: 500);

    $godown = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create([
        'name' => 'Store A',
        'code' => 'STA',
        'type' => 'store',
        'parent_id' => $godown->id,
    ]);

    $category = Category::create(['name' => 'Fabrics']);

    $low = Product::create([
        'name' => 'Low at Godown',
        'sku' => 'DASH-GD-'.Str::upper(Str::random(3)),
        'category_id' => $category->id,
        'godown_price' => 100,
        'shop_price' => 300,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
    app(StockService::class)->restock($godown->id, $low->id, null, 2, 100);

    $storeLow = Product::create([
        'name' => 'Low at Store',
        'sku' => 'DASH-ST-'.Str::upper(Str::random(3)),
        'category_id' => $category->id,
        'godown_price' => 100,
        'shop_price' => 300,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
    app(StockService::class)->restock($store->id, $storeLow->id, null, 3, 100);

    $zero = Product::create([
        'name' => 'Zero SKU',
        'sku' => 'DASH-ZERO-'.Str::upper(Str::random(3)),
        'godown_price' => 100,
        'shop_price' => 300,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
    Stock::forLocation($godown->id, $zero->id);

    CategoryThreshold::create([
        'category_id' => $category->id,
        'branch_id' => $store->id,
        'normal_capacity' => 5,
    ]);

    RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $storeLow->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Tier 1: one low row per (product, location), never aggregated
            ->has('alerts.product_alerts', 3)
            ->where('alerts.product_alerts.0.location_type', 'godown')
            ->where('alerts.product_alerts.0.sku', $zero->sku)
            ->where('alerts.product_alerts.1.location_name', $godown->name)
            ->where('alerts.product_alerts.1.location_type', 'godown')
            ->where('alerts.product_alerts.2.sku', $storeLow->sku)
            ->where('alerts.product_alerts.2.location_name', $store->name)
            ->where('alerts.product_alerts.2.location_type', 'store')
            // Tier 3: only godown rows, separated, with godown info
            ->where('alerts.godown_alerts', function ($rows) use ($godown, $low) {
                return count($rows) === 2
                    && $rows[0]['godown_id'] === $godown->id
                    && $rows[0]['godown_name'] === $godown->name
                    && $rows[1]['sku'] === $low->sku
                    && $rows[1]['product_name'] === $low->name;
            })
            // Tier 2: category roll-up per location
            ->where('alerts.category_alerts', fn ($rows) => collect($rows)->first() !== null)
            ->where('alerts.category_alerts.0.category', $category->name)
            ->where('alerts.category_alerts.0.location_id', $store->id)
            ->where('alerts.category_alerts.0.location_type', 'store')
            ->where('alerts.category_alerts.0.percent_of_normal', 60)
            // out of stock anywhere
            ->where('alerts.out_of_stock', 1)
            ->where('alerts.pending_stock_requests', 1)
            // location filter lists every location with its type
            ->where('locations', function ($rows) use ($store) {
                $rows = collect($rows);

                return $rows->count() === 2
                    && $rows->firstWhere('id', $store->id)['type'] === 'store'
                    && $rows->first(fn ($r) => $r['type'] === 'godown') !== null;
            }));
});

it('filters tiered stock alerts to a selected location', function () {
    $owner = salesOwner();
    restockProduct(qty: 50, shop: 500);

    $godown = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::create([
        'name' => 'Store A',
        'code' => 'STA',
        'type' => 'store',
        'parent_id' => $godown->id,
    ]);

    $low = Product::create([
        'name' => 'Low Store SKU',
        'sku' => 'DASH-FLT-'.Str::upper(Str::random(3)),
        'godown_price' => 100,
        'shop_price' => 300,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
    app(StockService::class)->restock($store->id, $low->id, null, 2, 100);

    $this->actingAs($owner)->get('/dashboard?branch='.$store->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts.product_alerts', 1)
            ->where('alerts.product_alerts.0.location_id', $store->id)
            ->where('alerts.godown_alerts', []));

    $this->actingAs($owner)->get('/dashboard?branch='.$godown->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.godown_alerts', fn ($rows) => count($rows) === 0));
});

it('summarises customer credit, deposits and new customers', function () {
    $owner = salesOwner();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    Customer::create([
        'branch_id' => $branch->id,
        'customer_no' => 'DASH-C1',
        'name' => 'Credit Client',
        'customer_type' => 'credit',
        'credit_limit' => 5000,
        'credit_balance' => 3000,
    ]);
    Customer::create([
        'branch_id' => $branch->id,
        'customer_no' => 'DASH-C2',
        'name' => 'Member Client',
        'customer_type' => 'member',
        'deposit_balance' => 700,
        'lifetime_spend' => 0,
        'loyalty_tier' => 'bronze',
    ]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.credit_outstanding', fn ($v) => (float) $v === 3000.0)
            ->where('customers.deposits_held', fn ($v) => (float) $v === 700.0)
            ->where('customers.new_customers', fn ($v) => (int) $v === 2)
            ->has('customers.top_customers', 2));
});

it('exposes the recent activity feed to users with activity permission', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 50, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ])->assertRedirect();

    $this->actingAs($owner)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.activity', true)
            ->where('activity', function ($items) {
                $items = collect($items);

                return $items->count() > 0
                    && str_contains($items->first()['description'], 'Completed sale');
            }));

    $other = Branch::create(['name' => 'Branch Two', 'code' => 'B2', 'currency' => 'TZS']);
    $seller = User::factory()->create(['branch_id' => $other->id]);
    $seller->assignRole(['Seller']);

    $this->actingAs($seller)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('can.activity', false));
});

it('scopes a seller to their own sales and omits financial data entirely', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 50, shop: 500);

    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertRedirect();

    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $seller = User::factory()->create(['branch_id' => $branch->id]);
    $seller->assignRole(['Seller']);

    $this->actingAs($seller)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 1500]],
    ])->assertRedirect();

    $this->actingAs($seller)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // own sales only — the owner's 1000 sale is invisible
            ->where('kpis.revenue.value', fn ($v) => (float) $v === 1500.0)
            ->where('kpis.transactions.value', fn ($v) => (int) $v === 1)
            // golden rule: financial data is never sent to a seller
            ->where('gross_profit', null)
            ->where('refunds', null)
            ->where('promotions', null)
            ->where('trend', [])
            ->where('payment_split', [])
            ->where('categories', [])
            ->where('hourly', [])
            ->where('weekdays', [])
            ->where('branches', [])
            ->where('top_products', [])
            ->where('activity', [])
            ->where('customers', null)
            // tier 2 & 3 stock alerts are withheld
            ->where('can.own_sales_only', true)
            ->where('can.view_financials', false)
            ->where('can.view_stock_tier2_tier3', false)
            ->where('can.view_cross_location', false)
            ->where('locations', []));
});

it('gives finance financials but never stock alerts, plus a finance-scoped audit feed', function () {
    $owner = salesOwner();
    $product = restockProduct(qty: 50, shop: 500);

    $finance = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
    $finance->assignRole(['Finance']);

    restockProduct(qty: 0, cost: 100, shop: 300);

    // Create a sale so the financial-scoped activity feed has at least one entry.
    $this->actingAs($owner)->post('/sales', [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 500,
        ]],
        'payments' => [['method' => 'cash', 'amount' => 500]],
    ]);

    $this->actingAs($finance)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // financials present (company-wide default via view-all-locations)
            ->where('can.view_financials', true)
            ->where('can.view_cross_location', true)
            ->where('gross_profit.amount', fn ($v) => is_numeric($v))
            ->where('gross_profit.margin', fn ($v) => is_numeric($v))
            // stock / operational data withheld
            ->where('can.view_stock_alerts', false)
            ->where('alerts', null)
            // finance sees the scoped financial activity feed
            ->where('can.view_activity', true)
            ->where('activity.0.event', 'sale')
            ->where('activity', fn ($events) => collect($events)->every(
                fn ($e) => in_array($e['event'], [
                    'sale', 'refund', 'refund_requested', 'sale_voided',
                    'credit_payment', 'deposit', 'loyalty_adjust',
                    'shift_closed', 'shift_reconciled', 'cash_movement',
                ], true)
            ))
            ->where('can.own_sales_only', false));
});
