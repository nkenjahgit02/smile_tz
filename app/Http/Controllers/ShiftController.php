<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Support\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('shifts/index', [
            'shifts' => Shift::query()
                ->with(['branch:id,name', 'user:id,name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'activeShift' => Shift::open()->where('branch_id', $request->user()->branch_id)->with('user:id,name')->first(),
            'branch' => $request->user()->branch?->only(['id', 'name', 'code']),
            'can' => [
                'open' => $request->user()->can('shift.open'),
                'close' => $request->user()->can('shift.close'),
            ],
            'filters' => $request->only('status'),
        ]);
    }

    public function open(Request $request)
    {
        abort_unless($request->user()->can('shift.open'), 403);

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $shift = $this->shifts->open(
                $request->user()->branch_id,
                $request->user(),
                $validated['opening_float'],
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('pos.index')->with('success', "Shift {$shift->shift_no} opened.");
    }

    public function close(Request $request, Shift $shift)
    {
        abort_unless($request->user()->can('shift.close'), 403);

        $validated = $request->validate([
            'closing_count' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->shifts->close($shift, $request->user(), $validated['closing_count'], $validated['notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('shifts.index')->with('success', "Shift {$shift->shift_no} closed.");
    }
}
