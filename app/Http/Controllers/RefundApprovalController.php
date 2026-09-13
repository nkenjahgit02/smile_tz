<?php

namespace App\Http\Controllers;

use App\Models\RefundRequest;
use App\Support\ActivityLogger;
use App\Support\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RefundApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $refunds = RefundRequest::query()
            ->with(['sale:id,sale_no', 'branch:id,name,currency', 'initiator:id,name', 'reviewer:id,name'])
            ->whereIn('branch_id', $user->scopedLocationIds())
            ->when($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected', 'auto_approved'], true), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end, id desc")
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/refunds', [
            'refunds' => $refunds,
            'filters' => $request->only('status'),
        ]);
    }

    public function approve(Request $request, RefundRequest $refundRequest)
    {
        abort_unless($request->user()->can('approve refunds'), 403);

        if (! in_array($refundRequest->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund request was already resolved.']);
        }

        $reviewer = $request->user();

        try {
            app(SaleService::class)->refund(
                $refundRequest->sale,
                $reviewer,
                'REFUND: '.$refundRequest->reason,
                $refundRequest->restock,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $refundRequest->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        ActivityLogger::log(
            "Approved refund of {$refundRequest->amount} on sale {$refundRequest->sale?->sale_no}",
            $refundRequest,
            'refund',
        );

        return back()->with('success', 'Refund approved and processed.');
    }

    public function reject(Request $request, RefundRequest $refundRequest)
    {
        abort_unless($request->user()->can('approve refunds'), 403);

        if (! in_array($refundRequest->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund request was already resolved.']);
        }

        $refundRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        ActivityLogger::log(
            "Rejected refund of {$refundRequest->amount} on sale {$refundRequest->sale?->sale_no}",
            $refundRequest,
            'refund',
        );

        return back()->with('success', 'Refund request rejected.');
    }
}
