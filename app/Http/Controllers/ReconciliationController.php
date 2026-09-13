<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Shift;
use App\Support\ShiftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $threshold = (float) Setting::number('finance.variance_threshold', 5000);

        $shifts = Shift::query()
            ->with(['branch:id,name', 'user:id,name', 'reviewer:id,name'])
            ->whereIn('branch_id', $user->scopedLocationIds())
            ->whereNotNull('closed_at')
            ->when($request->filled('status') && in_array($request->status, ['closed', 'reconciled'], true), fn ($q) => $q->where('status', $request->status))
            ->when($request->boolean('flagged'), fn ($q) => $q->whereRaw('abs(difference) > ?', [$threshold]))
            ->orderByDesc('closed_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/reconciliation', [
            'shifts' => $shifts,
            'threshold' => $threshold,
            'can_approve' => $user->can('approve shift reconciliation'),
            'filters' => $request->only(['status', 'flagged']),
        ]);
    }

    public function approve(Request $request, Shift $shift)
    {
        abort_unless($request->user()->can('approve shift reconciliation'), 403);

        if (! in_array($shift->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        app(ShiftService::class)->reconcile($shift, $request->user());

        return back()->with('success', "Shift {$shift->shift_no} reconciled.");
    }
}
