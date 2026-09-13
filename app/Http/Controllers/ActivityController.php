<?php

namespace App\Http\Controllers;

use App\Exports\ColumnarExport;
use App\Models\Branch;
use App\Models\User;
use App\Support\PdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityController extends Controller
{
    protected function applyFilters(Request $request)
    {
        return Activity::query()
            ->with('causer:id,name,email')
            ->when($request->filled('user_id'), fn ($q) => $q->where('causer_id', $request->integer('user_id')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', "%{$request->search}%"));
    }

    public function index(Request $request): Response
    {
        $logs = $this->applyFilters($request)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('activity/index', [
            'logs' => $logs,
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
            'events' => Activity::query()
                ->whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->values(),
            'roles' => Role::pluck('name')->values(),
            'filters' => $request->only(['user_id', 'role', 'event', 'branch_id', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $logs = $this->applyFilters($request)->latest()->get();

        $rows = $logs->map(fn (Activity $activity) => [
            'when' => $activity->created_at?->format('Y-m-d H:i:s'),
            'user' => $activity->causer?->name ?? 'System',
            'email' => $activity->causer?->email ?? ($activity->properties['email'] ?? ''),
            'role' => $activity->role ?? ($activity->properties['role'] ?? ''),
            'action' => $activity->description,
            'event' => $activity->event,
            'branch' => $activity->branch_id ?? '',
            'ip' => $activity->properties['ip_address'] ?? '',
            'user_agent' => $activity->properties['user_agent'] ?? '',
        ])->values();

        $format = $request->string('format', 'xlsx')->toString();

        if ($format === 'pdf') {
            $data = $rows->map(fn ($r) => (object) $r);

            return PdfService::stream('pdf.activity-log', [
                'logs' => $data,
                'filters' => $request->all(),
            ], 'activity-log.pdf');
        }

        return Excel::download(new ColumnarExport(
            ['Timestamp', 'User', 'Email', 'Role', 'Action', 'Event', 'Branch', 'IP Address', 'User Agent'],
            $rows->all(),
            'ActivityLog',
        ), match ($format) {
            'csv' => 'activity-log.csv',
            default => 'activity-log.xlsx',
        });
    }
}
