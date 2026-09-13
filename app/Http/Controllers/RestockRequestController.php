<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\RestockRequest;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RestockRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->input('status'), ['pending', 'dispatched', 'received', 'rejected'], true)
            ? $request->input('status')
            : 'pending';

        $requests = RestockRequest::query()
            ->with([
                'product:id,name,sku',
                'productVariant:id,name',
                'branch:id,name,type',
                'sourceBranch:id,name,type',
                'requester:id,name',
                'reviewer:id,name',
                'transfer',
            ])
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when(in_array($status, ['dispatched', 'received', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('stock/requests', [
            'requests' => $requests,
            'status' => $status,
            'counts' => [
                'pending' => RestockRequest::pending()->count(),
                'dispatched' => RestockRequest::where('status', 'dispatched')->count(),
                'received' => RestockRequest::where('status', 'received')->count(),
                'rejected' => RestockRequest::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'source_branch_id' => ['nullable', 'exists:branches,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);

        if ($branch->isGodown()) {
            return back()->withErrors(['branch_id' => 'Godowns supply stock — they do not request restock.'])
                ->withInput();
        }

        $sourceBranchId = $validated['source_branch_id'] ?? $branch->parent_id;

        if (! $sourceBranchId) {
            $sourceBranchId = Branch::godowns()->active()->orderBy('id')->value('id');
        }

        if (! $sourceBranchId) {
            return back()->withErrors(['source_branch_id' => 'No godown is available as a supply source yet. Create a godown first.'])
                ->withInput();
        }

        $source = Branch::find($sourceBranchId);

        if (! $source || ! $source->isGodown()) {
            return back()->withErrors(['source_branch_id' => 'Restock must be sourced from a godown.'])
                ->withInput();
        }

        if ($source->id === $branch->id) {
            return back()->withErrors(['source_branch_id' => 'A location cannot request stock from itself.'])
                ->withInput();
        }

        $pending = RestockRequest::query()
            ->where('status', 'pending')
            ->where('branch_id', $validated['branch_id'])
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->exists();

        if ($pending) {
            return back()->withErrors(['restock' => 'A restock request for this product is already pending approval.']);
        }

        $requestRecord = RestockRequest::create([
            'branch_id' => $validated['branch_id'],
            'source_branch_id' => $source->id,
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'] ?? null,
            'quantity' => $validated['quantity'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        $product = $requestRecord->product;
        $variant = $requestRecord->productVariant;

        $reviewers = User::permission('stock.restock.approve')->get();
        (new SystemNotification(
            "Restock requested: {$product?->name}".($variant ? " ({$variant->name})" : '')
                ." for {$branch->name} — {$validated['quantity']} units from {$source->name} by {$request->user()->name}.",
            '/stock/requests',
            category: 'stock',
            target_role: 'Admin',
        ))->deliver($reviewers);

        ActivityLogger::log("Restock request #{$requestRecord->id} for {$product?->name} ({$validated['quantity']} units) from {$source->name}", $requestRecord, 'restock_request');

        return back()->with('success', 'Restock request sent to management for approval.');
    }

    public function approve(Request $request, RestockRequest $restockRequest, StockService $stock): RedirectResponse
    {
        abort_unless($restockRequest->status === 'pending', 403, 'Only pending requests can be approved.');

        $source = $restockRequest->sourceBranch ?? $restockRequest->branch;

        if ($source->id === $restockRequest->branch_id) {
            return back()->withErrors(['restock' => 'Cannot dispatch stock back to the requesting location.']);
        }

        try {
            DB::transaction(function () use ($restockRequest, $source, $request, $stock) {
                $transfer = StockTransfer::create([
                    'transfer_no' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
                    'from_branch_id' => $source->id,
                    'to_branch_id' => $restockRequest->branch_id,
                    'restock_request_id' => $restockRequest->id,
                    'status' => 'draft',
                    'created_by' => $request->user()->id,
                ]);

                $transfer->items()->create([
                    'product_id' => $restockRequest->product_id,
                    'product_variant_id' => $restockRequest->product_variant_id,
                    'quantity' => $restockRequest->quantity,
                ]);

                $stock->transfer($transfer);

                $transfer->forceFill([
                    'status' => 'in_transit',
                    'shipped_at' => now()->toDateString(),
                ])->save();

                $restockRequest->forceFill([
                    'status' => 'dispatched',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ])->save();
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())
                ->with('error', $e->getMessage());
        }

        ActivityLogger::log("Dispatched restock request #{$restockRequest->id} — {$restockRequest->quantity} units from {$source->name} to {$restockRequest->branch?->name}", $restockRequest, 'approved');

        $restockRequest->refresh();

        $requester = $restockRequest->requester;
        (new SystemNotification(
            "Your restock request for {$restockRequest->product?->name} was approved — {$restockRequest->quantity} units dispatched from {$source->name}. Confirm receipt when the stock arrives.",
            '/stock/transfers',
            category: 'stock',
            target_user_id: $requester?->id,
        ))->deliver($requester);

        return back()->with('success', "Restock approved — stock dispatched from {$source->name}.");
    }

    public function reject(Request $request, RestockRequest $restockRequest): RedirectResponse
    {
        abort_unless($restockRequest->status === 'pending', 403, 'Only pending requests can be rejected.');

        $restockRequest->forceFill([
            'status' => 'rejected',
            'notes' => $request->input('notes') ?: $restockRequest->notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $requester = $restockRequest->requester;
        (new SystemNotification(
            "Your restock request for {$restockRequest->product?->name} was rejected.",
            '/stock/requests?status=pending',
            category: 'stock',
            target_user_id: $requester?->id,
        ))->deliver($requester);

        ActivityLogger::log("Rejected restock request #{$restockRequest->id} for {$restockRequest->product?->name}", $restockRequest, 'rejected');

        return back()->with('success', 'Restock request rejected.');
    }
}
