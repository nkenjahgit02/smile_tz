<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Setting;
use App\Models\Shift;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashDrawerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $locationIds = $user->scopedLocationIds();
        $threshold = (float) Setting::number('finance.variance_threshold', 5000);

        $movements = CashMovement::query()
            ->with(['branch:id,name', 'shift:id,shift_no', 'requester:id,name', 'approver:id,name'])
            ->whereIn('branch_id', $locationIds)
            ->when($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $shifts = Shift::query()
            ->with(['branch:id,name', 'user:id,name'])
            ->whereIn('branch_id', $locationIds)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->limit(20)
            ->get()
            ->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'shift_no' => $shift->shift_no,
                'status' => $shift->status,
                'opened_at' => $shift->opened_at?->toDateTimeString(),
                'closed_at' => $shift->closed_at?->toDateTimeString(),
                'opening_float' => (float) $shift->opening_float,
                'expected_in_drawer' => (float) $shift->expected_in_drawer,
                'difference' => (float) $shift->difference,
                'flagged' => abs((float) $shift->difference) > $threshold,
                'branch' => $shift->branch?->name,
                'cashier' => $shift->user?->name,
            ])
            ->all();

        return Inertia::render('finance/drawer', [
            'movements' => $movements,
            'shifts' => $shifts,
            'threshold' => $threshold,
            'can_request' => $user->can('shift.cash_in_out') || $user->can('manage petty cash'),
            'can_approve' => $user->can('manage petty cash'),
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('shift.cash_in_out') || $request->user()->can('manage petty cash'), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:cash_in,cash_out,safe_drop,petty_cash'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $isDeposit = $validated['type'] === 'cash_in';

        $movement = CashMovement::create([
            'branch_id' => $user->branch_id ?: ($user->scopedLocationIds()[0] ?? null),
            'shift_id' => $user->branch ? Shift::open()->where('branch_id', $user->branch_id)->value('id') : null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'reference' => $validated['reference'] ?? null,
            'status' => $isDeposit ? 'approved' : 'pending',
            'requested_by' => $user->id,
            'approved_by' => $isDeposit ? $user->id : null,
            'approved_at' => $isDeposit ? now() : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLogger::log(
            "{$movement->type} of {$movement->amount} on the {$movement->branch?->name} drawer",
            $movement,
            'cash_movement',
        );

        $message = $isDeposit
            ? 'Cash-in recorded on the drawer.'
            : 'Cash-out request sent to Finance for approval.';

        return back()->with('success', $message);
    }

    public function approve(Request $request, CashMovement $movement)
    {
        abort_unless($request->user()->can('manage petty cash'), 403);

        if (! in_array($movement->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($movement->status !== 'pending') {
            throw ValidationException::withMessages(['movement' => 'This movement was already resolved.']);
        }

        $movement->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLogger::log(
            "Approved {$movement->type} of {$movement->amount} requested by {$movement->requester?->name}",
            $movement,
            'cash_movement',
        );

        return back()->with('success', 'Cash movement approved.');
    }

    public function reject(Request $request, CashMovement $movement)
    {
        abort_unless($request->user()->can('manage petty cash'), 403);

        if (! in_array($movement->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($movement->status !== 'pending') {
            throw ValidationException::withMessages(['movement' => 'This movement was already resolved.']);
        }

        $movement->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLogger::log(
            "Rejected {$movement->type} of {$movement->amount} requested by {$movement->requester?->name}",
            $movement,
            'cash_movement',
        );

        return back()->with('success', 'Cash movement rejected.');
    }
}
