<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockTake;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Support\StockService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed();
});

function actingAsOwner()
{
    $owner = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $owner->assignRole(['Owner']);

    return $owner;
}

function makeProduct(?string $sku = null, float $godown = 100, float $shop = 150): Product
{
    $sku ??= 'SKU-'.uniqid();

    return Product::create([
        'name' => 'Test Product '.$sku,
        'sku' => $sku,
        'category_id' => null,
        'brand_id' => null,
        'unit_id' => null,
        'godown_price' => $godown,
        'shop_price' => $shop,
        'reorder_level' => 5,
        'is_active' => true,
    ]);
}

it('seeds roles, branches and permissions', function () {
    expect(Role::count())->toBe(7)
        ->and(Branch::count())->toBe(1)
        ->and(Permission::count())->toBe(75);
});

it('creates a product with variants via the controller', function () {
    $owner = actingAsOwner();
    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Mineral Water',
            'sku' => 'H2O-500',
            'barcode' => '8960001',
            'godown_price' => 800,
            'shop_price' => 1000,
            'reorder_level' => 10,
            'variants' => [
                ['name' => '500ml', 'sku' => 'H2O-500-A', 'godown_price' => 500, 'shop_price' => 700],
                ['name' => '1.5L', 'sku' => 'H2O-500-B', 'godown_price' => 1200, 'shop_price' => 1500],
            ],
        ])
        ->assertRedirect();

    $product = Product::where('sku', 'H2O-500')->first();
    expect($product)->not->toBeNull()
        ->and($product->variants()->count())->toBe(2)
        ->and(Activity::where('event', 'created')->exists())->toBeTrue();
});

it('returns matching products with effective variant prices via POS lookup', function () {
    $owner = actingAsOwner();
    $product = Product::create([
        'name' => 'Mineral Water',
        'sku' => 'H2O-LOOKUP',
        'category_id' => null,
        'brand_id' => null,
        'unit_id' => null,
        'godown_price' => 800,
        'shop_price' => 1000,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
    $product->variants()->create(['name' => '500ml', 'sku' => 'H2O-LOOKUP-A', 'godown_price' => 500, 'shop_price' => 700, 'is_active' => true]);
    $product->variants()->create(['name' => '1L', 'sku' => 'H2O-LOOKUP-B', 'godown_price' => 500, 'shop_price' => null, 'is_active' => true]);

    $payload = $this->actingAs($owner)
        ->postJson(route('products.lookup'), ['q' => 'H2O-LOOKUP'])
        ->assertOk()
        ->json();

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($product->id)
        ->and($payload[0]['name'])->toBe('Mineral Water')
        ->and($payload[0]['variants'])->toHaveCount(2)
        ->and(collect($payload[0]['variants'])->pluck('price', 'sku')->all())->toBe([
            'H2O-LOOKUP-A' => 700,
            'H2O-LOOKUP-B' => 1000, // falls back to base product shop_price
        ]);
});

it('receives a purchase order into stock with FIFO batches', function () {
    $owner = actingAsOwner();
    $branch = Branch::where('code', 'MAIN')->first();
    $supplier = Supplier::create(['name' => 'Acme Supplies']);
    $product = makeProduct();

    $po = PurchaseOrder::create([
        'order_no' => 'PO-TEST-1',
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'status' => 'approved',
        'created_by' => $owner->id,
    ]);
    $poItem = $po->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 100,
        'unit_cost' => 80,
    ]);

    // Receive 100 at cost 80
    $this->actingAs($owner)
        ->post(route('purchase-orders.receive', $po), [
            'items' => [
                ['id' => $poItem->id, 'received_quantity' => 100, 'unit_cost' => 80],
            ],
            'batch_number' => 'B1-2026',
            'expiry_date' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHas('success');

    $stock = Stock::where([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
    ])->first();

    expect($stock?->quantity)->toEqual(100.0)
        ->and($stock->batches()->count())->toBe(1)
        ->and($stock->batches()->first()->batch_number)->toBe('B1-2026')
        ->and($po->fresh()->status)->toBe('received');
});

it('consumes stock FIFO and computes cogs', function () {
    $branch = Branch::where('code', 'MAIN')->first();
    $product = makeProduct();

    app(StockService::class)->restock($branch->id, $product->id, null, 50, 100);
    app(StockService::class)->restock($branch->id, $product->id, null, 50, 200);

    $cogs = app(StockService::class)->consumeFifo($branch->id, $product->id, null, 60);

    expect($cogs)->toBe(7000.0) // 50*100 + 10*200
        ->and(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(40.0)
        ->and(StockBatch::query()->where('stock_id', $branch->id)->sum('quantity'))->toEqual(40.0);
});

it('adjusts stock downward with a reason', function () {
    $branch = Branch::where('code', 'MAIN')->first();
    $product = makeProduct();

    $service = app(StockService::class);
    $service->restock($branch->id, $product->id, null, 20, 100);
    $service->adjust($branch->id, $product->id, null, -5, 'damage');

    expect(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(15.0);
});

it('ships and receives a stock transfer between branches', function () {
    $owner = actingAsOwner();
    $branch = Branch::where('code', 'MAIN')->first();
    $branch2 = Branch::create(['name' => 'Branch Two', 'code' => 'SECOND', 'currency' => 'TZS', 'is_active' => true]);
    $product = makeProduct();

    $service = app(StockService::class);
    $service->restock($branch->id, $product->id, null, 30, 100);

    $transfer = StockTransfer::create([
        'transfer_no' => 'TRF-TEST-1',
        'from_branch_id' => $branch->id,
        'to_branch_id' => $branch2->id,
        'status' => 'draft',
        'created_by' => $owner->id,
    ]);
    $transfer->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 10,
    ]);

    $this->actingAs($owner)->post(route('stock-transfers.ship', $transfer))->assertSessionHas('success');
    expect(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(20.0)
        ->and($transfer->fresh()->status)->toBe('in_transit');

    $this->actingAs($owner)->post(route('stock-transfers.receive', $transfer))->assertSessionHas('success');
    expect(Stock::forLocation($branch2->id, $product->id)->quantity)->toEqual(10.0)
        ->and($transfer->fresh()->status)->toBe('received');
});

it('adjusts stock by approving a stock take variance', function () {
    $owner = actingAsOwner();
    $branch = Branch::where('code', 'MAIN')->first();
    $product = makeProduct();

    $service = app(StockService::class);
    $service->restock($branch->id, $product->id, null, 25, 100);

    $take = StockTake::create([
        'take_no' => 'ST-TEST-1',
        'branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $owner->id,
    ]);
    $takeItem = $take->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'system_quantity' => 25,
        'counted_quantity' => 22,
        'difference' => -3,
    ]);

    $this->actingAs($owner)->post(route('stock-takes.approve', $take))->assertSessionHas('success');

    expect(Stock::forLocation($branch->id, $product->id)->quantity)->toEqual(22.0)
        ->and($take->fresh()->status)->toBe('approved');
});

it('logs login and failed login events', function () {
    $owner = actingAsOwner();

    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect();

    expect(Activity::where('event', 'login')->count())->toBe(1);

    auth()->logout();

    $this->post('/login', ['email' => $owner->email, 'password' => 'wrong'])
        ->assertSessionHasErrors();

    expect(Activity::where('event', 'failed_login')->count())->toBe(1);
});
