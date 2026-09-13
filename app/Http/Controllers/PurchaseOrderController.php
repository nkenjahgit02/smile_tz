<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('purchase-orders/index', [
            'orders' => PurchaseOrder::query()
                ->with(['supplier:id,name', 'branch:id,name', 'items.product:id,name,sku'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
                ->when($request->filled('search'), fn ($q) => $q->where('order_no', 'like', "%{$request->search}%"))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'suppliers' => Supplier::select('id', 'name')->orderBy('name')->get(),
            'filters' => $request->only(['status', 'supplier_id', 'search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchase-orders/create', [
            'suppliers' => Supplier::select('id', 'name')->orderBy('name')->get(),
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $order = PurchaseOrder::create([
            'order_no' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'supplier_id' => $request->integer('supplier_id'),
            'branch_id' => $request->integer('branch_id'),
            'status' => 'draft',
            'expected_at' => $request->date('expected_at'),
            'notes' => $request->input('notes'),
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->input('items', []) as $item) {
            $order->items()->create($item);
        }

        ActivityLogger::log("Created purchase order {$order->order_no}", $order, 'created');

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $order): Response
    {
        $order->load([
            'supplier',
            'branch',
            'creator:id,name',
            'items.variant',
            'items.product:id,name,sku,unit_id',
        ]);

        return Inertia::render('purchase-orders/show', [
            'order' => $order,
            'canEdit' => in_array($order->status, ['draft']),
        ]);
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        abort_unless(in_array($order->status, ['draft']), 403, 'Only draft orders can be edited.');

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*._deleted' => ['boolean'],
        ]);

        $order->update([
            'supplier_id' => $validated['supplier_id'],
            'branch_id' => $validated['branch_id'],
            'expected_at' => $validated['expected_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $kept = [];
        foreach ($validated['items'] as $item) {
            if (! empty($item['_deleted'])) {
                if (! empty($item['id'])) {
                    $order->items()->whereKey($item['id'])->delete();
                }

                continue;
            }
            if (! empty($item['id'])) {
                $order->items()->whereKey($item['id'])->update($item);
                $kept[] = $item['id'];
            } else {
                unset($item['_deleted']);
                $item = $order->items()->create($item);
                $kept[] = $item->id;
            }
        }
        $order->items()->whereNotIn('id', $kept)->delete();

        ActivityLogger::log("Updated purchase order {$order->order_no}", $order, 'updated');

        return back()->with('success', 'Purchase order updated.');
    }

    public function cancel(PurchaseOrder $order)
    {
        abort_unless(in_array($order->status, ['draft', 'approved']), 403, 'Order cannot be cancelled.');

        $order->update(['status' => 'cancelled']);

        ActivityLogger::log("Cancelled purchase order {$order->order_no}", $order, 'cancelled');

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function approve(PurchaseOrder $order)
    {
        abort_unless($order->status === 'draft', 403, 'Only draft orders can be approved.');
        $this->authorize('purchase_orders.approve');

        $order->update(['status' => 'approved']);

        ActivityLogger::log("Approved purchase order {$order->order_no}", $order, 'approved');

        return back()->with('success', 'Purchase order approved.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $order, StockService $stock)
    {
        abort_unless(in_array($order->status, ['draft', 'approved']), 403, 'Order cannot be received.');

        $receivedCount = 0;

        foreach ($request->input('items', []) as $item) {
            $poItem = $order->items()->findOrFail($item['id']);
            $qty = $item['received_quantity'];

            if ($qty > 0) {
                $stock->restock(
                    $order->branch_id,
                    $poItem->product_id,
                    $poItem->product_variant_id,
                    $qty,
                    $item['unit_cost'] ?? $poItem->unit_cost,
                    $request->input('batch_number'),
                    $request->input('expiry_date'),
                );

                $poItem->forceFill([
                    'received_quantity' => $poItem->received_quantity + $qty,
                    'unit_cost' => $item['unit_cost'] ?? $poItem->unit_cost,
                ])->save();

                $receivedCount += $qty;
            }
        }

        if ($receivedCount > 0) {
            $order->update(['status' => 'received', 'received_at' => now()->toDateString()]);
        }

        ActivityLogger::log("Received stock on purchase order {$order->order_no} ({$receivedCount} units)", $order, 'received');

        return back()->with('success', "Received {$receivedCount} units into stock.");
    }
}
