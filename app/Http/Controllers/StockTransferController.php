<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Notifications\SystemNotification;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('stock/transfers', [
            'transfers' => StockTransfer::query()
                ->with(['fromBranch:id,name', 'toBranch:id,name', 'items', 'creator:id,name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('stock/transfers-create', [
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
            'products' => Product::with('variants:id,product_id,name')
                ->select('id', 'name', 'sku', 'unit_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'exists:branches,id', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $transfer = StockTransfer::create([
            'transfer_no' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'from_branch_id' => $request->integer('from_branch_id'),
            'to_branch_id' => $request->integer('to_branch_id'),
            'status' => 'draft',
            'notes' => $request->input('notes'),
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->input('items', []) as $item) {
            unset($item['product']);
            $transfer->items()->create($item);
        }

        ActivityLogger::log("Created stock transfer {$transfer->transfer_no}", $transfer, 'created');

        return back()->with('success', 'Stock transfer created.');
    }

    public function show(StockTransfer $transfer): Response
    {
        $transfer->load([
            'fromBranch', 'toBranch', 'creator', 'items.variant',
            'items.product:id,name,sku',
        ]);

        return Inertia::render('stock/transfers-show', [
            'transfer' => $transfer,
        ]);
    }

    public function ship(StockTransfer $transfer, StockService $stock)
    {
        abort_unless($transfer->status === 'draft', 403, 'Only draft transfers can be shipped.');

        $stock->transfer($transfer);

        $transfer->forceFill([
            'status' => 'in_transit',
            'shipped_at' => now()->toDateString(),
        ])->save();

        ActivityLogger::log("Shipped stock transfer {$transfer->transfer_no}", $transfer, 'shipped');

        return back()->with('success', 'Transfer shipped — source branch stock deducted.');
    }

    public function receive(StockTransfer $transfer, StockService $stock)
    {
        abort_unless($transfer->status === 'in_transit', 403, 'Only in-transit transfers can be received.');

        $stock->receiveTransfer($transfer);

        if ($transfer->restock_request_id) {
            $restockRequest = $transfer->restockRequest;
            if ($restockRequest && $restockRequest->status === 'dispatched') {
                $restockRequest->forceFill(['status' => 'received'])->save();

                $requester = $restockRequest->requester;
                (new SystemNotification(
                    "Restock request #{$restockRequest->id} for {$restockRequest->product?->name} was received — {$restockRequest->quantity} units added at {$restockRequest->branch?->name}.",
                    '/stock/requests?status=received',
                    category: 'stock',
                    target_user_id: $requester?->id,
                ))->deliver($requester);

                ActivityLogger::log("Restock request #{$restockRequest->id} received at {$restockRequest->branch?->name}", $restockRequest, 'received');
            }
        }

        ActivityLogger::log("Received stock transfer {$transfer->transfer_no}", $transfer, 'received');

        return back()->with('success', 'Transfer received — destination branch stock updated.');
    }

    public function cancel(StockTransfer $transfer)
    {
        abort_unless(in_array($transfer->status, ['draft', 'in_transit']), 403, 'Transfer cannot be cancelled.');

        $transfer->update(['status' => 'cancelled']);

        ActivityLogger::log("Cancelled stock transfer {$transfer->transfer_no}", $transfer, 'cancelled');

        return back()->with('success', 'Transfer cancelled.');
    }
}
