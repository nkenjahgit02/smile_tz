<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRule;
use App\Models\Branch;
use App\Models\BranchOrder;
use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\OrderService;
use App\Support\PdfService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scoped = $user->scopedLocationIds();

        $query = BranchOrder::query()
            ->with(['branch:id,name', 'godown:id,name', 'creator:id,name', 'items'])
            ->withCount('items')
            ->where(fn ($q) => $q->whereIn('branch_id', $scoped)->orWhereIn('godown_id', $scoped));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority') && in_array($request->priority, ['normal', 'urgent'], true)) {
            $query->where('priority', $request->priority);
        }
        if ($request->boolean('mine') && ! $user->can('view-all-locations')) {
            $query->where('created_by', $user->id);
        }

        $orders = $query->orderByDesc('id')->get()->map(
            fn (BranchOrder $order) => $this->present($order),
        )->values()->all();

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'pipeline' => $this->pipeline($user),
            'filters' => $request->only('status', 'priority', 'mine'),
            'locations' => Branch::select('id', 'name', 'code', 'type')
                ->whereIn('id', $scoped)->where('is_active', true)
                ->orderBy('name')->get(),
            'godowns' => Branch::whereIn('id', $scoped)->where('type', 'godown')->where('is_active', true)
                ->select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('orders/create', [
            'godowns' => Branch::where('type', 'godown')->where('is_active', true)
                ->select('id', 'name', 'code')->orderBy('name')->get(),
            'products' => Product::where('is_active', true)
                ->select('id', 'name', 'sku', 'unit_id')->orderBy('name')
                ->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'godown_id' => ['required', 'integer'],
            'priority' => ['required', 'in:normal,urgent'],
            'required_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $branchId = $this->activeBranchId($user);

        $existing = $this->consumedKey('branch_order', $validated['idempotency_key'] ?? null, $user);
        if ($existing) {
            return redirect()->route('orders.show', $existing)->with('info', 'Order already created.');
        }

        $order = $this->orders->createDraft(
            $user,
            $branchId,
            (int) $validated['godown_id'],
            $validated['items'],
            $validated,
        );

        $this->markKey('branch_order', $validated['idempotency_key'] ?? null, $user, $order);
        ActivityLogger::log("Created branch order {$order->order_number}", $order, 'order_created');

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} saved as draft.");
    }

    public function show(Request $request, BranchOrder $order)
    {
        $this->assertVisible($order, $request->user());

        return Inertia::render('orders/show', [
            'order' => $this->present($order->loadMissing(['items.product' => fn ($q) => $q->select('id', 'name'), 'history', 'branch:id,name', 'godown:id,name', 'creator:id,name'])),
            'current_stock' => $this->currentStock($request->user()->scopedLocationIds(), $order),
        ]);
    }

    public function submit(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.create');
        $validated = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $existing = $this->consumedKey('branch_order_submit', $validated['idempotency_key'] ?? null, $request->user());
        if ($existing && $existing->is($order)) {
            // The order already left the draft stage thanks to a retried submit.
            return redirect()->route('orders.show', $order)->with('info', 'Order already submitted.');
        }

        $this->orders->submit($order, $request->user());
        $this->markKey('branch_order_submit', $validated['idempotency_key'] ?? null, $request->user(), $order);

        ActivityLogger::log("Submitted order {$order->order_number} for approval", $order, 'order_submitted');

        return redirect()->route('orders.show', $order)->with('success', 'Order submitted for approval.');
    }

    public function approve(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.approve');
        $this->assertLocationAccess($order, $request->user(), 'branch');

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->orders->approve($order, $request->user(), $validated['items'] ?? null, $validated['reason'] ?? null);

        ActivityLogger::log("Approved order {$order->order_number}", $order, 'order_approved');

        return back()->with('success', 'Order approval recorded.');
    }

    public function reject(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.approve');
        $this->assertLocationAccess($order, $request->user(), 'branch');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orders->reject($order, $request->user(), $validated['reason']);
        ActivityLogger::log("Rejected order {$order->order_number}", $order, 'order_rejected');

        return back()->with('success', 'Order rejected.');
    }

    public function returnForChanges(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.approve');
        $this->assertLocationAccess($order, $request->user(), 'branch');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orders->returnForChanges($order, $request->user(), $validated['reason']);
        ActivityLogger::log("Returned order {$order->order_number} for changes", $order, 'order_returned_for_changes');

        return back()->with('success', 'Order returned to the branch for changes.');
    }

    public function startPicking(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'godown.picking');
        $this->assertLocationAccess($order, $request->user(), 'godown');

        $this->orders->startPicking($order, $request->user());
        ActivityLogger::log("Started picking for order {$order->order_number}", $order, 'picking_started');

        return back()->with('success', 'Picking started.');
    }

    public function completePicking(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'godown.picking');
        $this->assertLocationAccess($order, $request->user(), 'godown');

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
        ]);

        $this->orders->completePicking($order, $request->user(), $validated['items']);
        ActivityLogger::log("Completed picking for order {$order->order_number}", $order, 'picking_completed');

        return back()->with('success', 'Picking completed — ready for dispatch.');
    }

    public function dispatch(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'godown.dispatch');
        $this->assertLocationAccess($order, $request->user(), 'godown');

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'dispatch_note_no' => ['nullable', 'string', 'max:40'],
        ]);

        $this->orders->dispatch($order, $request->user(), $validated['items'] ?? [], $validated['dispatch_note_no'] ?? null);
        ActivityLogger::log("Dispatched order {$order->order_number}", $order, 'order_dispatched');

        return back()->with('success', 'Order dispatched.');
    }

    public function dispatchNote(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'godown.dispatch');
        $this->assertLocationAccess($order, $request->user(), 'godown');

        return PdfService::stream('pdf.dispatch-note', [
            'order' => $order->loadMissing(['branch', 'godown', 'items.product', 'dispatcher']),
        ], "dispatch-note-{$order->order_number}.pdf");
    }

    public function receive(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.receive');
        $this->assertLocationAccess($order, $request->user(), 'branch');

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.received' => ['required', 'numeric', 'min:0'],
            'items.*.damaged' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->orders->receive($order, $request->user(), $validated['items']);
        ActivityLogger::log("Confirmed receipt for order {$order->order_number}", $order, 'order_received');

        return back()->with('success', 'Receipt confirmed.');
    }

    public function resolve(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.review');

        $validated = $request->validate(['resolution' => ['required', 'string', 'max:2000']]);
        $this->orders->resolveDiscrepancy($order, $request->user(), $validated['resolution']);
        ActivityLogger::log("Resolved discrepancy on order {$order->order_number}", $order, 'discrepancy_resolved');

        return back()->with('success', 'Discrepancy resolved — order closed.');
    }

    public function cancel(Request $request, BranchOrder $order)
    {
        $this->assertCanEdit($order, $request->user(), 'orders.cancel');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orders->cancel($order, $request->user(), $validated['reason']);
        ActivityLogger::log("Cancelled order {$order->order_number}", $order, 'order_cancelled');

        return back()->with('success', 'Order cancelled.');
    }

    public function rules(Request $request)
    {
        abort_unless($request->user()->can('orders.approve'), 403);

        $rules = ApprovalRule::query()
            ->where('scope', 'branch_order')
            ->orderBy('threshold')
            ->get()
            ->map(fn (ApprovalRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'threshold' => (float) $rule->threshold,
                'unit' => $rule->unit,
                'required_role' => $rule->required_role,
                'enabled' => $rule->enabled,
            ]);

        return Inertia::render('orders/rules', ['rules' => $rules]);
    }

    // ------------------------------------------------------------------

    private function activeBranchId(User $user): int
    {
        $scoped = $user->scopedLocationIds();
        $store = Branch::whereIn('id', $scoped)->where('type', '!=', 'godown')->where('is_active', true)->first();

        return $store?->id ?: (int) $scoped[0];
    }

    private function assertVisible(BranchOrder $order, User $user): void
    {
        if ($user->can('view-all-locations')) {
            return;
        }
        if (! in_array($order->branch_id, $user->scopedLocationIds(), true) && ! in_array($order->godown_id, $user->scopedLocationIds(), true)) {
            abort(403);
        }
    }

    private function assertCanEdit(BranchOrder $order, User $user, string $permission): void
    {
        abort_unless($user->can($permission), 403);
        $this->assertVisible($order, $user);
    }

    private function assertLocationAccess(BranchOrder $order, User $user, string $side): void
    {
        if ($user->can('view-all-locations')) {
            return;
        }
        $ids = $user->scopedLocationIds();
        $expected = $side === 'branch' ? $order->branch_id : $order->godown_id;
        if (! in_array($expected, $ids, true)) {
            throw ValidationException::withMessages(['status' => "You are not assigned to the {$side} of this order."]);
        }
    }

    private function consumedKey(string $scope, ?string $key, User $user): mixed
    {
        if (! $key) {
            return null;
        }

        return IdempotencyKey::where('scope', $scope)->where('key', $key)->where('user_id', $user->id)->first()?->model;
    }

    private function markKey(string $scope, ?string $key, User $user, BranchOrder $order): void
    {
        if (! $key) {
            return;
        }
        IdempotencyKey::firstOrCreate(
            ['scope' => $scope, 'key' => $key, 'user_id' => $user->id],
            ['model_type' => $order->getMorphClass(), 'model_id' => $order->id, 'created_at' => now()],
        );
    }

    private function pipeline(User $user): array
    {
        $scoped = $user->scopedLocationIds();
        $stages = [];
        foreach (BranchOrder::PIPELINE_STAGES as $stage) {
            $stages[$stage] = BranchOrder::where('status', $stage)
                ->where(fn ($q) => $q->whereIn('branch_id', $scoped)->orWhereIn('godown_id', $scoped))
                ->count();
        }

        return $stages;
    }

    private function currentStock(array $locationIds, BranchOrder $order): array
    {
        $rows = Stock::query()
            ->with('branch:id,name,type')
            ->whereIn('branch_id', $locationIds)
            ->whereIn('product_id', $order->items->pluck('product_id'))
            ->get();

        return $rows->map(fn (Stock $row) => [
            'branch' => $row->branch?->name,
            'type' => $row->branch?->type,
            'product_id' => $row->product_id,
            'quantity' => (float) $row->quantity,
            'reserved' => (float) $row->reserved,
            'in_transit' => (float) $row->in_transit_quantity,
            'available' => $row->available(),
        ])->all();
    }

    private function present(BranchOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'branch_id' => $order->branch_id,
            'godown_id' => $order->godown_id,
            'priority' => $order->priority,
            'required_date' => $order->required_date?->toDateString(),
            'status' => $order->status,
            'notes' => $order->notes,
            'created_by' => $order->created_by,
            'requires_second_approval' => (bool) $order->requires_second_approval,
            'approved_at' => $order->approved_at?->toDateTimeString(),
            'dispatch_note_no' => $order->dispatch_note_no,
            'dispatched_at' => $order->dispatched_at?->toDateTimeString(),
            'received_at' => $order->received_at?->toDateTimeString(),
            'picked_qty' => (float) $order->items->sum('qty_picked'),
            'branch' => $order->branch ? ['id' => $order->branch->id, 'name' => $order->branch->name, 'code' => $order->branch->code] : null,
            'godown' => $order->godown ? ['id' => $order->godown->id, 'name' => $order->godown->name, 'code' => $order->godown->code] : null,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_name' => $i->product?->name,
                'qty_requested' => (float) $i->qty_requested,
                'qty_approved' => $i->qty_approved === null ? null : (float) $i->qty_approved,
                'qty_picked' => $i->qty_picked === null ? null : (float) $i->qty_picked,
                'qty_dispatched' => $i->qty_dispatched === null ? null : (float) $i->qty_dispatched,
                'qty_received' => $i->qty_received === null ? null : (float) $i->qty_received,
                'qty_damaged' => $i->qty_damaged === null ? null : (float) $i->qty_damaged,
                'qty_shortage' => $i->qty_shortage === null ? null : (float) $i->qty_shortage,
                'unit_cost' => $i->unit_cost === null ? null : (float) $i->unit_cost,
            ])->values()->all(),
            'history' => ($order->relationLoaded('history') ? $order->history : collect())->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'user' => $h->user?->name,
                'reason' => $h->reason,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }
}
