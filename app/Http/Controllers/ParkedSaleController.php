<?php

namespace App\Http\Controllers;

use App\Models\ParkedSale;
use App\Models\Product;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ParkedSaleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('sales/parked', [
            'parked' => ParkedSale::query()
                ->where('branch_id', $request->user()->branch_id)
                ->whereNull('recalled_at')
                ->with(['user:id,name', 'customer:id,name'])
                ->orderByDesc('held_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('sales.park'), 403);

        $validated = $request->validate([
            'cart' => ['required', 'array', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        $parked = ParkedSale::create([
            'branch_id' => $request->user()->branch_id,
            'user_id' => $request->user()->id,
            'customer_id' => $validated['customer_id'] ?? null,
            'parked_no' => 'PK-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'cart_json' => $validated['cart'],
            'reference' => $validated['reference'] ?? null,
            'held_at' => now(),
        ]);

        ActivityLogger::log("Parked sale {$parked->parked_no}", $parked, 'sale_parked');

        return back()->with('success', "Sale parked as {$parked->parked_no}.");
    }

    public function recall(Request $request, ParkedSale $parked)
    {
        abort_unless($request->user()->can('sales.park'), 403);
        abort_unless($parked->branch_id === $request->user()->branch_id, 403);
        abort_unless($parked->recalled_at === null, 403, 'This parked sale was already recalled.');

        $itemRows = collect($parked->cart_json['items'] ?? [])->map(function (array $row) use ($request) {
            $product = Product::find($row['product_id'] ?? null);
            if (! $product) {
                return null;
            }

            $variant = collect($product->variants)->where('id', $row['product_variant_id'] ?? null)->first();
            $stock = app(StockService::class);

            return [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id ?? null,
                'name' => $product->name,
                'variant_name' => $variant->name ?? null,
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['price'] ?? ($variant->price ?? $product->shop_price)),
                'catalog_price' => (float) ($variant->price ?? $product->shop_price),
                'discount' => (float) ($row['discount'] ?? 0),
                'average_cost' => $stock->averageUnitCost(
                    $request->user()->branch_id,
                    $product->id,
                    $variant->id ?? null,
                ),
            ];
        })->filter()->values();

        $parked->update(['recalled_at' => now()]);

        ActivityLogger::log("Recalled parked sale {$parked->parked_no}", $parked, 'sale_recalled');

        return response()->json([
            'id' => $parked->id,
            'parked_no' => $parked->parked_no,
            'reference' => $parked->reference,
            'customer_id' => $parked->customer_id,
            'items' => $itemRows,
        ]);
    }

    public function destroy(Request $request, ParkedSale $parked)
    {
        $parked->delete();

        ActivityLogger::log("Deleted parked sale {$parked->parked_no}");

        return back()->with('success', 'Parked sale discarded.');
    }
}
