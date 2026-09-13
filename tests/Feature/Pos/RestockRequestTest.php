<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\StockService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

if (! function_exists('restockOwner')) {
    function restockOwner(): User
    {
        $user = User::factory()->create([
            'branch_id' => Branch::where('code', 'MAIN')->value('id'),
            'password' => 'password',
        ]);
        $user->assignRole(['Owner']);

        return $user;
    }
}

if (! function_exists('godownStoreProduct')) {
    function godownStoreProduct(float $godownQty = 20): array
    {
        $godown = Branch::where('code', 'MAIN')->firstOrFail();
        $store = Branch::firstOrCreate(['code' => 'STA'], [
            'name' => 'Store A',
            'type' => 'store',
            'parent_id' => $godown->id,
        ]);
        $product = Product::create([
            'name' => 'Restock SKU '.Str::upper(Str::random(4)),
            'sku' => 'RESTOCK-'.Str::upper(Str::random(4)),
            'godown_price' => 100,
            'shop_price' => 500,
            'reorder_level' => 15,
            'is_active' => true,
        ]);

        app(StockService::class)->restock($godown->id, $product->id, null, $godownQty, 100);

        return [$godown, $store, $product];
    }
}

if (! function_exists('restockSeller')) {
    function restockSeller(?int $branchId = null): User
    {
        $seller = User::factory()->create([
            'branch_id' => $branchId ?? Branch::where('code', 'STA')->value('id'),
            'password' => 'password',
        ]);
        $seller->assignRole(['Seller']);

        return $seller;
    }
}

it('lets a store submit a restock request sourced from its godown and notifies admins', function () {
    Notification::fake();
    [$godown, $store, $product] = godownStoreProduct();
    $seller = restockSeller($store->id);
    $owner = restockOwner();

    $this->actingAs($seller)->post('/stock/restock-requests', [
        'product_id' => $product->id,
        'branch_id' => $store->id,
        'quantity' => 11,
    ])->assertRedirect();

    $request = RestockRequest::firstOrFail();
    expect($request->status)->toBe('pending')
        ->and($request->requested_by)->toBe($seller->id)
        ->and($request->source_branch_id)->toBe($godown->id)
        ->and((float) $request->quantity)->toBe(11.0);

    Notification::assertSentTo(
        $owner,
        SystemNotification::class,
        fn (SystemNotification $n) => str_contains($n->message, 'Restock requested'),
    );
});

it('lets a store without a designated godown request from any active godown', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $store->forceFill(['parent_id' => null])->save();
    $seller = restockSeller($store->id);

    $this->actingAs($seller)->post('/stock/restock-requests', [
        'product_id' => $product->id,
        'branch_id' => $store->id,
        'quantity' => 5,
    ])->assertRedirect();

    expect(RestockRequest::firstOrFail()->source_branch_id)->toBe($godown->id);
});

it('refuses restock requests from a godown', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $owner = restockOwner();

    $this->actingAs($owner)->post('/stock/restock-requests', [
        'product_id' => $product->id,
        'branch_id' => $godown->id,
        'quantity' => 5,
    ])->assertSessionHasErrors('branch_id');

    expect(RestockRequest::count())->toBe(0);
});

it('blocks a duplicate pending request for the same product and store', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $seller = restockSeller($store->id);

    RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($seller)->post('/stock/restock-requests', [
        'product_id' => $product->id,
        'branch_id' => $store->id,
        'quantity' => 11,
    ])->assertSessionHasErrors('restock');

    expect(RestockRequest::count())->toBe(1);
});

it('blocks approval from a user without the restock permission', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $seller = restockSeller($store->id);

    $request = RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($seller)
        ->patch(route('stock.restock-requests.approve', $request))
        ->assertForbidden();
});

it('approving dispatches stock out of the godown and notifies the requester', function () {
    Notification::fake();
    [$godown, $store, $product] = godownStoreProduct(godownQty: 20);
    $seller = restockSeller($store->id);
    $owner = restockOwner();

    $request = RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('stock.restock-requests.approve', $request))
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe('dispatched')
        ->and($request->reviewed_by)->toBe($owner->id);

    $transfer = StockTransfer::firstOrFail();
    expect($transfer->restock_request_id)->toBe($request->id)
        ->and($transfer->from_branch_id)->toBe($godown->id)
        ->and($transfer->to_branch_id)->toBe($store->id)
        ->and($transfer->status)->toBe('in_transit');

    $godownStock = Stock::forLocation($godown->id, $product->id);
    $storeStock = Stock::forLocation($store->id, $product->id);
    expect((float) $godownStock->quantity)->toBe(9.0)
        ->and((float) $storeStock->quantity)->toBe(0.0);

    Notification::assertSentTo(
        $seller,
        SystemNotification::class,
        fn (SystemNotification $n) => str_contains($n->message, 'dispatched from'),
    );
});

it('receiving the dispatch transfer adds stock at the store', function () {
    Notification::fake();
    [$godown, $store, $product] = godownStoreProduct(godownQty: 20);
    $seller = restockSeller($store->id);
    $owner = restockOwner();

    $request = RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($owner)->patch(route('stock.restock-requests.approve', $request))->assertRedirect();
    $transfer = StockTransfer::firstOrFail();

    $storeAdmin = User::factory()->create([
        'branch_id' => $store->id,
        'password' => 'password',
    ]);
    $storeAdmin->assignRole(['Admin']);

    $this->actingAs($storeAdmin)
        ->post(route('stock-transfers.receive', $transfer))
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe('received')
        ->and((float) Stock::forLocation($store->id, $product->id)->quantity)->toBe(11.0);
});

it('does not dispatch when the godown lacks enough stock', function () {
    [$godown, $store, $product] = godownStoreProduct(godownQty: 4);
    $seller = restockSeller($store->id);
    $owner = restockOwner();

    $request = RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('stock.restock-requests.approve', $request))
        ->assertSessionHasErrors('stock');

    $request->refresh();
    expect($request->status)->toBe('pending')
        ->and(StockTransfer::count())->toBe(0)
        ->and((float) Stock::forLocation($godown->id, $product->id)->quantity)->toBe(4.0);
});

it('rejects a restock request without changing stock and notifies the requester', function () {
    Notification::fake();
    [$godown, $store, $product] = godownStoreProduct(godownQty: 20);
    $seller = restockSeller($store->id);
    $owner = restockOwner();

    $request = RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('stock.restock-requests.reject', $request))
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe('rejected')
        ->and($request->reviewed_by)->toBe($owner->id)
        ->and((float) Stock::forLocation($godown->id, $product->id)->quantity)->toBe(20.0);

    Notification::assertSentTo(
        $seller,
        SystemNotification::class,
        fn (SystemNotification $n) => str_contains($n->message, 'was rejected'),
    );
});

it('notifies admins when a sale pushes stock below the reorder level', function () {
    Notification::fake();
    $godown = Branch::where('code', 'MAIN')->firstOrFail();
    $owner = restockOwner();
    $product = Product::create([
        'name' => 'Low Crossing SKU '.Str::upper(Str::random(4)),
        'sku' => 'CROSS-'.Str::upper(Str::random(4)),
        'godown_price' => 100,
        'shop_price' => 200,
        'reorder_level' => 15,
        'is_active' => true,
    ]);
    app(StockService::class)->restock($godown->id, $product->id, null, 20, 100);

    $itemSubtotal = round(17 * (float) $product->shop_price, 2);

    $this->actingAs($owner)->post('/sales', [
        'customer_type' => 'walk_in',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 17, 'shop_price' => (float) $product->shop_price],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => $itemSubtotal],
        ],
        'total_amount' => $itemSubtotal,
    ])->assertRedirect();

    Notification::assertSentTo(
        $owner,
        SystemNotification::class,
        fn (SystemNotification $n) => str_contains($n->message, 'Low stock')
            && str_contains($n->message, $product->name),
    );
});

it('blocks sellers from viewing the stock requests page', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $seller = restockSeller($store->id);

    $this->actingAs($seller)->get('/stock/requests')->assertForbidden();
});

it('shows pending restock requests with counts for approvers', function () {
    [$godown, $store, $product] = godownStoreProduct();
    $owner = restockOwner();
    $seller = restockSeller($store->id);

    RestockRequest::create([
        'branch_id' => $store->id,
        'source_branch_id' => $godown->id,
        'product_id' => $product->id,
        'quantity' => 11,
        'status' => 'pending',
        'requested_by' => $seller->id,
    ]);

    $this->actingAs($owner)
        ->get('/stock/requests?status=pending')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stock/requests')
            ->has('requests.data', 1)
            ->where('status', 'pending')
            ->where('counts.pending', 1)
            ->where('counts.dispatched', 0),
        );
});
