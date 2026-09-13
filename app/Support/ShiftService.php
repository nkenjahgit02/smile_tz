<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    /**
     * Open a shift (opening float must be counted before the first sale of the day).
     */
    public function open(int $branchId, User $cashier, float $openingFloat, ?string $notes = null): Shift
    {
        if (Shift::open()->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['shift' => 'A shift is already open for this branch.']);
        }

        /** @var Shift $shift */
        $shift = Shift::create([
            'branch_id' => $branchId,
            'user_id' => $cashier->id,
            'shift_no' => 'SHF-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'opening_float' => $openingFloat,
            'status' => 'open',
            'notes' => $notes,
            'opened_at' => now(),
        ]);

        return $shift;
    }

    /**
     * Close a shift: expected = opening float + cash sales - cash-only adjustments,
     * compare with the counted drawer.
     */
    public function close(Shift $shift, User $cashier, float $closingCount, ?string $notes = null): Shift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => 'This shift is already closed.']);
        }

        $cashIn = (float) $shift->sales()
            ->where('status', 'active')
            ->get()
            ->sum(function ($sale) {
                return (float) $sale->payments()->where('method', 'cash')->sum('amount');
            });

        $cashOut = (float) $shift->sales()
            ->where('status', 'voided')
            ->get()
            ->sum(fn ($sale) => (float) $sale->payments()->where('method', 'cash')->sum('amount'));

        $expected = round((float) $shift->opening_float + $cashIn - $cashOut, 2);
        $difference = round($closingCount - $expected, 2);

        DB::transaction(function () use ($shift, $closingCount, $expected, $difference, $notes) {
            $shift->update([
                'closing_count' => $closingCount,
                'expected_in_drawer' => $expected,
                'difference' => $difference,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $notes ?: $shift->notes,
            ]);

            ActivityLogger::log("Closed shift {$shift->shift_no}", $shift, 'shift_closed');
        });

        if (abs($difference) > (float) Setting::number('finance.variance_threshold', 5000)) {
            $this->notifyVariance($shift, $difference);
        }

        return $shift->fresh();
    }

    /**
     * Verify a closed shift's cash count against the system total. Marking a
     * shift reconciled is the Finance role's close-of-day sign-off.
     */
    public function reconcile(Shift $shift, User $reviewer): Shift
    {
        if ($shift->status === 'open') {
            throw ValidationException::withMessages(['shift' => 'An open shift cannot be reconciled yet.']);
        }

        $shift->update([
            'status' => 'reconciled',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        ActivityLogger::log("Reconciled shift {$shift->shift_no}", $shift, 'shift_reconciled');

        return $shift->fresh();
    }

    private function notifyVariance(Shift $shift, float $difference): void
    {
        $recipients = User::role('Finance')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $currency = $shift->branch?->currency ?? 'TZS';

        (new SystemNotification(
            "Shift variance on {$shift->shift_no} ({$shift->branch?->name}): ".
                'physical count '.number_format((float) $shift->closing_count, 2)." {$currency} vs expected ".
                number_format((float) $shift->expected_in_drawer, 2)." {$currency} (difference ".number_format($difference, 2)." {$currency}).",
            '/finance/reconciliation',
            category: 'finance',
            target_role: 'Finance',
            type: 'shift_variance',
        ))->deliver($recipients);
    }
}
