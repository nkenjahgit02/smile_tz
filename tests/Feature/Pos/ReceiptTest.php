<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ReceiptCopy;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Support\SaleService;
use App\Support\StockService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

if (! function_exists('receiptOwner')) {
    function receiptOwner(array $attrs = []): User
    {
        $owner = User::factory()->create(array_merge([
            'branch_id' => Branch::where('code', 'MAIN')->value('id'),
            'password' => 'password',
        ], $attrs));
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

it('bakes receipt settings into the immutable snapshot payload', function () {
    Setting::set('receipt.paper_width', '76', 'pos');
    Setting::set('receipt.header_lines', "TIN: 123-456-789\nVAT Reg: 987", 'pos');
    Setting::set('receipt.footer_message', 'Goods once sold are not returnable.', 'pos');
    Setting::set('receipt.auto_print', false, 'pos');

    $owner = receiptOwner();
    $product = restockProduct(qty: 10, cost: 100, shop: 500);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 500]],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();
    $payload = app(SaleService::class)->buildReceiptPayload($sale);

    expect($payload['settings']['paper_width_mm'])->toEqual(76)
        ->and($payload['settings']['header_lines'])->toContain('TIN: 123-456-789')
        ->and($payload['settings']['footer_message'])->toEqual('Goods once sold are not returnable.')
        ->and($payload['settings']['auto_print'])->toBeFalse();
});

it('serves an 80mm thermal document on GET sales/{id}/receipt and snapshots it for the audit trail', function () {
    Setting::set('receipt.paper_width', 80, 'pos');
    Setting::set('receipt.header_lines', 'TIN: 123-456-789', 'pos');
    Setting::set('receipt.footer_message', 'Thank you for your purchase!', 'pos');

    $owner = receiptOwner();
    $product = restockProduct(qty: 10, cost: 100, shop: 500);

    $this->actingAs($owner)->post('/sales', checkoutPayload(
        items: [['product_id' => $product->id, 'quantity' => 2]],
        payments: [['method' => 'card', 'amount' => 1000, 'reference' => 'REF-1']],
    ))->assertRedirect();

    $sale = Sale::firstOrFail();

    $beforePrint = ReceiptCopy::where('sale_id', $sale->id)->count();

    $this->actingAs($owner)->get("/sales/{$sale->id}/receipt")
        ->assertOk()
        ->assertSee('width: 80mm', false)
        ->assertSee('TIN: 123-456-789')
        ->assertSee('Thank you for your purchase!')
        ->assertSee($sale->sale_no)
        ->assertSee($product->name)
        ->assertSee('TOTAL (TZS)');

    expect(ReceiptCopy::where('sale_id', $sale->id)->count())->toEqual($beforePrint + 1);

    $snapshot = ReceiptCopy::where('sale_id', $sale->id)->latest('id')->first()->content_snapshot;
    expect($snapshot['settings']['paper_width_mm'])->toEqual(80)
        ->and($snapshot['settings']['header_lines'])->toContain('TIN: 123-456-789');
});

it('blocks a thermal receipt for a sale outside the users location scope', function () {
    $manager = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $manager->assignRole(['branch-manager']);
    $other = Branch::create(['name' => 'Branch Two', 'code' => 'B2', 'currency' => 'TZS']);

    $sale = Sale::create([
        'branch_id' => $other->id,
        'user_id' => $manager->id,
        'sale_no' => 'SALE-403',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 0,
        'amount_paid' => 0,
        'change_amount' => 0,
        'status' => 'active',
        'payment_status' => 'paid',
    ]);

    $this->actingAs($manager)->get("/sales/{$sale->id}/receipt")->assertForbidden();
});

it('lets a settings manager update receipt printer settings', function () {
    $owner = receiptOwner();

    $this->actingAs($owner)->put('/settings/receipt', [
        'paper_width' => 76,
        'header_lines' => "Company name\nTIN: 999",
        'footer_message' => 'Returns accepted within 7 days.',
        'auto_print' => 1,
    ])->assertRedirect();

    expect((int) Setting::number('receipt.paper_width'))->toEqual(76)
        ->and(Setting::get('receipt.header_lines'))->toContain('TIN: 999')
        ->and(Setting::get('receipt.footer_message'))->toEqual('Returns accepted within 7 days.')
        ->and(Setting::bool('receipt.auto_print'))->toBeTrue();
});

it('rejects invalid paper widths', function () {
    $owner = receiptOwner();

    $this->actingAs($owner)->put('/settings/receipt', [
        'paper_width' => 63,
    ])->assertSessionHasErrors('paper_width');
});

it('forbids receipt settings for users without settings.manage', function () {
    $seller = User::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'password' => 'password',
    ]);
    $seller->assignRole(['Seller']);

    $this->actingAs($seller)->get('/settings/receipt')->assertForbidden();
    $this->actingAs($seller)->put('/settings/receipt', [
        'paper_width' => 58,
    ])->assertForbidden();
});
