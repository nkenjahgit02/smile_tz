<?php

use App\Models\Branch;
use App\Models\User;
use App\Notifications\SystemNotification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed();
});

it('hides stock-category notifications from the finance bell', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $finance = User::factory()->create(['branch_id' => $branch->id]);
    $finance->assignRole(['Finance']);

    $payout = new SystemNotification('A refund was approved.', null, 'finance', 'Finance');
    $lowStock = new SystemNotification('Low stock on Maize Flour.', null, 'stock', 'Admin', target_location_id: $branch->id);

    $payout->deliver($finance);
    $lowStock->deliver($finance);

    $this->actingAs($finance)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.latest.0.message', 'A refund was approved.'));
});

it('hides finance/sales-management noise from the seller bell', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $seller = User::factory()->create(['branch_id' => $branch->id]);
    $seller->assignRole(['Seller']);

    $system = new SystemNotification('A new admin account was created.', null, 'system', 'Admin');
    $finance = new SystemNotification('Daily finance summary is ready.', null, 'finance', 'Finance');
    $ownSale = new SystemNotification('Your shift just closed.', null, 'sales');

    $system->deliver($seller);
    $finance->deliver($seller);
    $ownSale->deliver($seller);

    $this->actingAs($seller)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.latest.0.message', 'Your shift just closed.'));
});

it('lets owner and admin see every notification regardless of category or target', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole(['Admin']);

    (new SystemNotification('Low stock.', null, 'stock', 'Admin', target_location_id: $branch->id))->deliver($admin);
    (new SystemNotification('Refund reviewed.', null, 'finance', 'Finance'))->deliver($admin);
    (new SystemNotification('Owner changed a setting.', null, 'system', 'Admin'))->deliver($admin);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unread_count', 3));
});

it('targets tier-1 low-stock alerts only to staff linked to that location', function () {
    $godown = Branch::where('code', 'MAIN')->firstOrFail();
    $other = Branch::create(['name' => 'Branch Two', 'code' => 'B2', 'currency' => 'TZS']);

    $owner = User::factory()->create(['branch_id' => $godown->id]);
    $owner->assignRole(['Owner']);

    $admin = User::factory()->create(['branch_id' => $godown->id]);
    $admin->assignRole(['Admin']);

    $finance = User::factory()->create(['branch_id' => $godown->id]);
    $finance->assignRole(['Finance']);

    $localSeller = User::factory()->create(['branch_id' => $godown->id]);
    $localSeller->assignRole(['Seller']);

    $remoteSeller = User::factory()->create(['branch_id' => $other->id]);
    $remoteSeller->assignRole(['Seller']);

    $recipients = User::recipientsForLocation($godown->id)->pluck('id');

    expect($recipients)->toContain($owner->id)
        ->and($recipients)->toContain($admin->id)
        ->and($recipients)->toContain($localSeller->id)
        ->and($recipients)->not->toContain($finance->id)
        ->and($recipients)->not->toContain($remoteSeller->id);
});
