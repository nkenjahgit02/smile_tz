<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditTrailController extends Controller
{
    /**
     * Financial event types only — never the full system activity feed
     * (logins, product edits, etc.) that Owner/Admin see.
     */
    private const FINANCIAL_EVENTS = [
        'sale', 'refund', 'refund_requested', 'sale_voided',
        'credit_payment', 'deposit', 'loyalty_adjust',
        'shift_closed', 'shift_reconciled', 'cash_movement',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $activities = Activity::query()
            ->with('causer:id,name', 'subject')
            ->whereIn('event', self::FINANCIAL_EVENTS)
            ->whereIn('branch_id', $user->scopedLocationIds())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('event') && in_array($request->event, self::FINANCIAL_EVENTS, true), fn ($q) => $q->where('event', $request->event))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/audit', [
            'activities' => $activities->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'user' => $activity->causer?->name ?? 'System',
                'branch_id' => $activity->branch_id,
                'created_at' => $activity->created_at?->toDateTimeString(),
            ]),
            'events' => self::FINANCIAL_EVENTS,
            'filters' => $request->only(['from', 'to', 'event']),
        ]);
    }
}
