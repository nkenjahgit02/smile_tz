<?php

use App\Models\ApprovalRule;
use App\Models\Branch;
use App\Models\BranchOrder;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\OrderService;
use App\Support\StockService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed();
});

function makeDispatchableBranch(): array
{
    $godown = Branch::where('code', 'MAIN')->firstOrFail();
    $store = Branch::firstOrCreate(['code' => 'ARU'], [
        'name' => 'Arusha Store',
        'type' => 'store',
        'parent_id' => $godown->id,
    ]);
    $product = Product::create([
        'name' => 'Order SKU '.Str::upper(Str::random(4)),
        'sku' => 'ORDER-'.Str::upper(Str::random(4)),
        'godown_price' => 100,
        'shop_price' => 500,
        'reorder_level' => 5,
        'is_active' => true,
    ]);

    app(StockService::class)->restock($godown->id, $product->id, null, 50, 100);
    app(StockService::class)->restock($store->id, $product->id, null, 10, 120);

    return [$godown, $store, $product];
}

function orderUser(string $role, int $branchId): User
{
    $user = User::factory()->create([
        'branch_id' => $branchId,
        'is_active' => true,
        'password' => 'password',
    ]);
    $user->assignRole([$role]);
    $user->locations()->sync([$branchId]);

    return $user;
}

function placeOrder(User $seller, array $scaffold, float $qty = 10): BranchOrder
{
    [$godown, $store, $product] = $scaffold;

    return app(OrderService::class)->createDraft($seller, $store->id, $godown->id, [
        ['product_id' => $product->id, 'quantity' => $qty],
    ]);
}

it('creates a draft order and records the transition', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);

    $order = placeOrder($seller, [$godown, $store, $product]);

    expect($order->status)->toBe('draft')
        ->and($order->branch_id)->toBe($store->id)
        ->and($order->godown_id)->toBe($godown->id)
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->items->first()->qty_requested)->toBe(10.0)
        ->and($order->history)->toHaveCount(1)
        ->and($order->history->first()->to_status)->toBe('draft')
        ->and(StockMovement::count())->toBe(2); // only the two restocks, not the order
});

it('submits and routes a notification to the branch manager', function () {
    Notification::fake();
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);

    $order = placeOrder($seller, [$godown, $store, $product]);
    app(OrderService::class)->submit($order, $seller);

    expect($order->fresh()->status)->toBe('pending_approval')
        ->and($order->fresh()->history()->where('to_status', 'pending_approval')->count())->toBe(1);

    Notification::assertSentTo($manager, SystemNotification::class);
});

it('approves a small order without a second approval', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);

    $order = placeOrder($seller, [$godown, $store, $product], 1);
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager);

    expect($order->fresh()->status)->toBe('approved')
        ->and($order->fresh()->requires_second_approval)->toBeFalse()
        ->and($order->fresh()->approved_by)->toBe($manager->id)
        ->and((float) $order->items->first()->qty_approved)->toBe(1.0);
});

it('requires a second approval when the order value clears the threshold', function () {
    ApprovalRule::updateOrCreate(['scope' => 'branch_order', 'branch_id' => null], [
        'name' => 'High value',
        'threshold' => 500,
        'unit' => 'value',
        'required_role' => 'godown-manager',
        'enabled' => true,
    ]);
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $gm = orderUser('godown-manager', $godown->id);

    $order = placeOrder($seller, [$godown, $store, $product], 10); // 10 × 120 avg cost? unit_cost from store stock = 120
    app(OrderService::class)->submit($order, $seller);

    app(OrderService::class)->approve($order, $manager);
    expect($order->fresh()->status)->toBe('pending_approval')
        ->and($order->fresh()->requires_second_approval)->toBeTrue();

    // Second approval by the godown manager releases the order.
    app(OrderService::class)->approve($order, $gm);
    expect($order->fresh()->status)->toBe('approved')
        ->and($order->fresh()->second_approved_by)->toBe($gm->id);
});

it('rejects, returns for changes, and supports partial approval', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $order = placeOrder($seller, [$godown, $store, $product], 10);

    // Return to draft.
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->returnForChanges($order, $manager, 'Missing barcodes');
    expect($order->fresh()->status)->toBe('draft');

    // Resubmit and partially approve 6 of 10.
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager, [['item_id' => $order->items->first()->id, 'qty' => 6]]);

    expect($order->fresh()->status)->toBe('partially_approved')
        ->and((float) $order->items->first()->qty_approved)->toBe(6.0);

    // Reject a later order.
    $order2 = placeOrder($seller, [$godown, $store, $product], 5);
    app(OrderService::class)->submit($order2, $seller);
    app(OrderService::class)->reject($order2, $manager, 'Not needed');
    expect($order2->fresh()->status)->toBe('rejected');
});

it('dispatches only after picking and enforces godown availability', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $staff = orderUser('godown-staff', $godown->id);

    $order = placeOrder($seller, [$godown, $store, $product], 10);
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager);

    // Cannot dispatch before picking finishes.
    expect(fn () => app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]))
        ->toThrow(ValidationException::class);

    app(OrderService::class)->startPicking($order, $staff);
    app(OrderService::class)->completePicking($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);

    expect($order->fresh()->status)->toBe('ready_for_dispatch');

    // More than the godown has available must be rejected.
    $source = Stock::forLocation($godown->id, $product->id, null);
    expect(fn () => app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => $source->available() + 1]]))
        ->toThrow(ValidationException::class);

    app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);
    expect($order->fresh()->status)->toBe('dispatched');
});

it('moves stock and writes ledger rows with order references on dispatch', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $staff = orderUser('godown-staff', $godown->id);

    $godownStockBefore = (float) Stock::forLocation($godown->id, $product->id, null)->quantity;

    $order = placeOrder($seller, [$godown, $store, $product], 10);
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager);
    app(OrderService::class)->startPicking($order, $staff);
    app(OrderService::class)->completePicking($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);
    app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);

    $godownRow = Stock::forLocation($godown->id, $product->id, null);
    $branchRow = Stock::forLocation($store->id, $product->id, null);

    expect((float) $godownRow->quantity)->toBe($godownStockBefore - 10)
        ->and((float) $godownRow->in_transit_quantity)->toBe(10.0)
        ->and((float) $branchRow->in_transit_quantity)->toBe(10.0)
        ->and((float) $order->items->first()->qty_dispatched)->toBe(10.0);

    $movement = StockMovement::where('type', 'order_dispatch')->first();
    expect($movement)->not->toBeNull()
        ->and($movement->reference_type)->toBe($order->getMorphClass())
        ->and($movement->reference_id)->toBe($order->id)
        ->and($movement->branch_id)->toBe($godown->id)
        ->and((float) $movement->quantity)->toBe(-10.0);
});

it('receives a full delivery and closes the order', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $staff = orderUser('godown-staff', $godown->id);
    $receiver = orderUser('branch-manager', $store->id);

    $branchQtyBefore = (float) Stock::forLocation($store->id, $product->id, null)->quantity;

    $order = placeOrder($seller, [$godown, $store, $product], 10);
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager);
    app(OrderService::class)->startPicking($order, $staff);
    app(OrderService::class)->completePicking($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);
    app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);

    app(OrderService::class)->receive($order, $receiver, [['item_id' => $order->items->first()->id, 'received' => 10, 'damaged' => 0]]);

    $branchRow = Stock::forLocation($store->id, $product->id, null);
    $godownRow = Stock::forLocation($godown->id, $product->id, null);

    expect($order->fresh()->status)->toBe('closed')
        ->and((float) $branchRow->quantity)->toBe($branchQtyBefore + 10)
        ->and((float) $branchRow->in_transit_quantity)->toBe(0.0)
        ->and((float) $godownRow->in_transit_quantity)->toBe(0.0);

    $receiveMovement = StockMovement::where('type', 'receive')->where('reference_id', $order->id)->first();
    expect($receiveMovement)->not->toBeNull()
        ->and((float) $receiveMovement->quantity)->toBe(10.0)
        ->and($receiveMovement->branch_id)->toBe($store->id);
});

it('routes receipt shortages into discrepancy review and closure', function () {
    Notification::fake();
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $manager = orderUser('branch-manager', $store->id);
    $gm = orderUser('godown-manager', $godown->id);
    $staff = orderUser('godown-staff', $godown->id);

    $order = placeOrder($seller, [$godown, $store, $product], 10);
    app(OrderService::class)->submit($order, $seller);
    app(OrderService::class)->approve($order, $manager);
    app(OrderService::class)->startPicking($order, $staff);
    app(OrderService::class)->completePicking($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);
    app(OrderService::class)->dispatch($order, $staff, [['item_id' => $order->items->first()->id, 'qty' => 10]]);

    app(OrderService::class)->receive($order, $gm, [['item_id' => $order->items->first()->id, 'received' => 8, 'damaged' => 1, 'reason' => 'two units short during trucking']]);

    expect($order->fresh()->status)->toBe('discrepancy_review')
        ->and((float) $order->items->first()->qty_shortage)->toBe(1.0)
        ->and((float) $order->items->first()->qty_damaged)->toBe(1.0);

    Notification::assertSentTo(
        collect([$manager, $gm])->unique('id')->all(),
        SystemNotification::class,
    );

    app(OrderService::class)->resolveDiscrepancy($order, $gm, 'Carrier accepted liability; credit to be issued.');
    expect($order->fresh()->status)->toBe('closed');
});

it('cancels a draft order cleanly', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);

    $order = placeOrder($seller, [$godown, $store, $product], 10);
    app(OrderService::class)->cancel($order, $seller, 'No longer required');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->cancellation_reason)->toBe('No longer required');
});

it('enforces role gates on order actions over HTTP', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);
    $order = placeOrder($seller, [$godown, $store, $product], 3);
    app(OrderService::class)->submit($order, $seller);

    // A seller cannot approve.
    $this->actingAs($seller)->post(
        route('orders.approve', $order),
        ['items' => [['item_id' => $order->items->first()->id, 'qty' => 3]]],
    )->assertForbidden();

    // A godown staff user cannot approve either (approve is a branch-side action).
    $staff = orderUser('godown-staff', $godown->id);
    $this->actingAs($staff)->post(route('orders.approve', $order))->assertForbidden();
});

it('is idempotent on submit with the same idempotency key', function () {
    [$godown, $store, $product] = makeDispatchableBranch();
    $seller = orderUser('Seller', $store->id);

    $order = placeOrder($seller, [$godown, $store, $product], 3);

    $this->actingAs($seller)->post(route('orders.submit', $order), ['idempotency_key' => 'submit-dup-key'])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe('pending_approval')
        ->and($order->fresh()->history()->count())->toBe(2); // draft + pending_approval

    // Replaying the same key short-circuits: no new history row, no re-submit.
    $this->actingAs($seller)->post(route('orders.submit', $order), ['idempotency_key' => 'submit-dup-key'])
        ->assertRedirect(route('orders.show', $order));
    expect($order->fresh()->history()->count())->toBe(2);

    // Service-level resubmission of a non-draft order is rejected outright.
    expect(fn () => app(OrderService::class)->submit($order->fresh(), $seller))
        ->toThrow(ValidationException::class);
});
