<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTake;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StockTakeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('stock/takes', [
            'takes' => StockTake::query()
                ->with(['branch:id,name', 'creator:id,name', 'approver:id,name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('stock/takes-create', [
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
            'products' => Product::with('variants:id,product_id,name')
                ->select('id', 'name', 'sku')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function commence(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $take = StockTake::create([
            'take_no' => 'ST-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'branch_id' => $validated['branch_id'],
            'status' => 'counted',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
            'counted_at' => now(),
        ]);

        $products = Product::with(['variants', 'stock' => fn ($q) => $q->where('branch_id', $take->branch_id)])
            ->whereHas('stock', fn ($q) => $q->where('branch_id', $take->branch_id)->where('quantity', '>', 0))
            ->orWhereHas('variants.stock', fn ($q) => $q->where('branch_id', $take->branch_id)->where('quantity', '>', 0))
            ->get();

        foreach ($products as $product) {
            if ($product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $row = $variant->stock->firstWhere('branch_id', $take->branch_id);
                    $system = $row->quantity ?? 0;
                    $take->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'system_quantity' => $system,
                        'counted_quantity' => $system,
                        'difference' => 0,
                    ]);
                }
            } else {
                $row = $product->stock->firstWhere('branch_id', $take->branch_id);
                $system = $row->quantity ?? 0;
                if ($system > 0) {
                    $take->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'system_quantity' => $system,
                        'counted_quantity' => $system,
                        'difference' => 0,
                    ]);
                }
            }
        }

        ActivityLogger::log("Commenced stock take {$take->take_no}", $take, 'created');

        return redirect()->route('stock-takes.show', $take)->with('success', 'Stock take started. Enter counted quantities.');
    }

    public function show(StockTake $take): Response
    {
        $take->load(['branch:id,name', 'creator:id,name', 'approver:id,name', 'items.variant', 'items.product:id,name,sku']);

        return Inertia::render('stock/takes-show', [
            'take' => $take,
        ]);
    }

    public function submitCounts(Request $request, StockTake $take)
    {
        abort_unless($take->status === 'counted', 403, 'Only open counts can be submitted.');

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:stock_take_items,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['items'] as $item) {
            $takeItem = $take->items()->findOrFail($item['id']);
            $diff = round($item['counted_quantity'] - $takeItem->system_quantity, 3);
            $takeItem->update([
                'counted_quantity' => $item['counted_quantity'],
                'difference' => $diff,
                'reason' => $item['reason'] ?? null,
            ]);
        }

        $take->update(['status' => 'pending']);

        ActivityLogger::log("Submitted counts for stock take {$take->take_no}", $take, 'updated');

        return back()->with('success', 'Counts submitted — pending approval.');
    }

    public function approve(StockTake $take, StockService $stock)
    {
        abort_unless($take->status === 'pending', 403, 'Only pending counts can be approved.');
        abort_unless(auth()->user()->can('stock.take.approve'), 403, 'You are not allowed to approve stock takes.');

        foreach ($take->items as $item) {
            if ($item->difference != 0) {
                $stock->adjust(
                    $take->branch_id,
                    $item->product_id,
                    $item->product_variant_id,
                    $item->difference,
                    "stock take {$take->take_no}",
                );
            }
        }

        $take->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ActivityLogger::log("Approved stock take {$take->take_no}", $take, 'approved');

        return back()->with('success', 'Stock take approved — variances applied.');
    }

    public function cancel(StockTake $take)
    {
        abort_unless(in_array($take->status, ['counted', 'pending']), 403, 'Take cannot be cancelled.');

        $take->update(['status' => 'cancelled']);

        ActivityLogger::log("Cancelled stock take {$take->take_no}", $take, 'cancelled');

        return back()->with('success', 'Stock take cancelled.');
    }
}
