<?php

namespace App\Http\Controllers;

use App\Models\IdempotencyKey;
use App\Models\RefundRequest;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\ActivityLogger;
use App\Support\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('sales.view'), 403);

        return Inertia::render('sales/index', [
            'sales' => Sale::query()
                ->with(['branch:id,name', 'user:id,name', 'customer:id,name', 'items.product:id,name'])
                ->whereIn('branch_id', $user->scopedLocationIds())
                ->when($user->hasRole('Seller'), fn ($q) => $q->where('user_id', $user->id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('sale_no', 'like', "%{$request->search}%")
                            ->orWhere('receipt_no', 'like', "%{$request->search}%")
                            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$request->search}%"));
                    });
                })
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('search', 'status', 'user_id'),
        ]);
    }

    public function pos(Request $request): Response
    {
        $branch = $request->user()->branch;

        return Inertia::render('sales/pos', [
            'branch' => $branch?->only(['id', 'name', 'code', 'currency']),
            'active_shift' => $this->activeShift($request->user()),
            'can' => [
                'price_override' => $request->user()->can('sales.price_override'),
                'discount' => $request->user()->can('sales.discount'),
                'layaway' => $request->user()->can('sales.layaway'),
                'park' => $request->user()->can('sales.park'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user->can('sales.create')) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.price_override_reason' => ['nullable', 'string', 'max:255'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.loss_reason' => ['nullable', 'string', 'max:255'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,mobile,bank_transfer,loyalty,credit,deposit'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'layaway_deposit' => ['nullable', 'numeric', 'min:0'],
            'reward_id' => ['nullable', 'exists:loyalty_rewards,id'],
            'price_reduction_reason' => ['nullable', 'string', 'in:discount,loss,overpayment'],
            'price_reduction_amount' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        if (! empty($validated['idempotency_key'])) {
            $existing = IdempotencyKey::where('scope', 'sale')->where('user_id', $user->id)
                ->where('key', $validated['idempotency_key'])->first();
            if ($existing?->model) {
                return redirect()->route('sales.show', $existing->model)
                    ->with('info', 'This transaction was already completed.');
            }
        }

        try {
            $sale = $this->sales->checkout(
                branchId: $user->branch_id,
                cashier: $user,
                items: $validated['items'] ?? [],
                payments: $validated['payments'] ?? [],
                customerId: $validated['customer_id'] ?? null,
                shiftId: $this->activeShift($user)?->id,
                couponCode: $validated['coupon_code'] ?? null,
                options: [
                    'layaway_deposit' => $validated['layaway_deposit'] ?? 0,
                    'reward_id' => $validated['reward_id'] ?? null,
                    'price_reduction_reason' => $validated['price_reduction_reason'] ?? null,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                ],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if (! empty($validated['idempotency_key'])) {
            IdempotencyKey::firstOrCreate(
                ['scope' => 'sale', 'key' => $validated['idempotency_key'], 'user_id' => $user->id],
                ['model_type' => $sale->getMorphClass(), 'model_id' => $sale->id, 'created_at' => now()],
            );
        }

        ActivityLogger::log("Completed sale {$sale->sale_no}", $sale, 'sale');

        return redirect()->route('sales.show', $sale)->with([
            'success' => 'Sale completed.',
            'sale_completed' => $sale->id,
        ]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        $user = $request->user();

        abort_unless($user->can('sales.view'), 403);

        if (! in_array($sale->branch_id, $user->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($user->hasRole('Seller') && $sale->user_id !== $user->id) {
            abort(403);
        }

        $sale->load(['branch', 'user', 'customer', 'items.product', 'items.productVariant', 'payments', 'coupons', 'layaway', 'shift']);

        return Inertia::render('sales/show', [
            'sale' => $sale,
            'receipt' => $this->sales->buildReceiptPayload($sale),
            'can_void' => $user->can('sales.void'),
            'just_completed' => $request->session()->get('sale_completed') === $sale->id,
        ]);
    }

    /**
     * Thermal-printer print job for a sale. Standalone 80 mm document (no app
     * shell) that opens the browser print dialog immediately. Every print is
     * snapshotted to the receipt audit trail, the same as a reprint.
     */
    public function receipt(Request $request, Sale $sale)
    {
        $user = $request->user();

        if (! in_array($sale->branch_id, $user->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($user->hasRole('Seller') && $sale->user_id !== $user->id) {
            abort(403);
        }

        $template = $sale->receiptCopies()->latest('id')->first();
        $snapshot = $template?->content_snapshot ?: $this->sales->buildReceiptPayload($sale);

        // Persist a fresh copy for the audit trail (still rendering the stored snapshot).
        $this->sales->snapshotReceiptFromPayload($sale, $user, $snapshot);

        $settings = array_merge([
            'paper_width_mm' => 80,
            'header_lines' => [],
            'footer_message' => 'Thank you for your purchase!',
            'auto_print' => true,
        ], $snapshot['settings'] ?? []);

        return response()
            ->view('pdf.receipt', [
                'sale_no' => $sale->sale_no,
                'receipt' => $snapshot,
                'settings' => $settings,
                'currency' => $snapshot['branch']['currency'] ?? 'TZS',
            ])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Reprint an immutable receipt — never re-computed, always from the snapshot.
     */
    public function reprint(Request $request, Sale $sale)
    {
        if (! in_array($sale->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        $template = $sale->receiptCopies()->latest('id')->first();
        $snapshot = $template?->content_snapshot ?: $this->sales->buildReceiptPayload($sale);

        // Persist a fresh copy for the audit trail (still rendering the stored snapshot).
        $this->sales->snapshotReceiptFromPayload($sale, $request->user(), $snapshot);

        return Inertia::render('sales/receipt', [
            'sale_no' => $sale->sale_no,
            'receipt' => $snapshot,
        ]);
    }

    public function refund(Request $request, Sale $sale)
    {
        abort_unless($request->user()->can('sales.refund'), 403);

        if (! in_array($sale->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        abort_unless($sale->status === 'active', 403, 'Only active sales can be refunded.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.$sale->amount_paid],
            'reason' => ['required', 'string', 'max:1000'],
            'restock' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $amount = (float) $validated['amount'];
        $restock = (bool) ($validated['restock'] ?? true);
        $limit = (float) Setting::number('finance.refund_auto_approve_limit', 50000);

        if ($amount <= $limit) {
            // Small, routine refunds are auto-approved and processed immediately.
            $this->sales->refund($sale, $user, 'REFUND: '.$validated['reason'], $restock);

            RefundRequest::create([
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'initiated_by' => $user->id,
                'amount' => $amount,
                'reason' => $validated['reason'],
                'status' => 'auto_approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'restock' => $restock,
            ]);

            ActivityLogger::log("Refunded {$amount} for sale {$sale->sale_no}", $sale, 'refund');

            return back()->with('success', 'Refund processed.');
        }

        // Larger refunds wait for Finance approval before any money moves.
        $refundRequest = RefundRequest::create([
            'sale_id' => $sale->id,
            'branch_id' => $sale->branch_id,
            'initiated_by' => $user->id,
            'amount' => $amount,
            'reason' => $validated['reason'],
            'status' => 'pending',
            'restock' => $restock,
        ]);

        $this->notifyFinanceRefundPending($refundRequest);

        ActivityLogger::log("Requested refund of {$amount} for sale {$sale->sale_no}", $sale, 'refund_requested');

        return back()->with('success', 'Refund request sent to Finance for approval.');
    }

    public function void(Request $request, Sale $sale)
    {
        abort_unless($request->user()->can('sales.void'), 403);

        if (! in_array($sale->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $this->sales->void($sale, $user, $validated['reason']);

        if ($this->voidIsFlagged($sale, $user)) {
            $sale->update(['is_void_flagged' => true]);
            $this->notifyFinanceVoidFlagged($sale);
        }

        ActivityLogger::log("Voided sale {$sale->sale_no}", $sale, 'sale_voided');

        return back()->with('success', 'Sale voided.');
    }

    /**
     * A void is flagged when its value exceeds the configured amount threshold
     * or the same cashier has voided repeatedly within a single day.
     */
    public function destroy(Request $request, Sale $sale)
    {
        abort_unless($request->user()->can('sales.delete'), 403);

        if (! in_array($sale->branch_id, $request->user()->scopedLocationIds(), true)) {
            abort(403);
        }

        if ($sale->status === 'voided') {
            abort(422, 'Sale is already voided — delete is not required.');
        }

        $validated = $request->validate([
            'deletion_reason' => ['required', 'string', 'max:1000'],
        ]);

        $sale->delete();
        $sale->deleted_by = $request->user()->id;
        $sale->deletion_reason = $validated['deletion_reason'];
        $sale->save();

        ActivityLogger::log(
            "Deleted sale {$sale->sale_no}: {$validated['deletion_reason']}",
            $sale,
            'sale_deleted',
        );

        return back()->with('success', 'Sale permanently removed.');
    }

    private function voidIsFlagged(Sale $sale, User $user): bool
    {
        $amountThreshold = (float) Setting::number('finance.void_flag_amount', 200000);
        $dailyCountThreshold = (int) Setting::number('finance.void_flag_daily_count', 3);

        if ((float) $sale->amount_paid > $amountThreshold) {
            return true;
        }

        $voidsToday = Sale::where('status', 'voided')
            ->where('voided_by', $user->id)
            ->whereDate('voided_at', now()->toDateString())
            ->count();

        return $voidsToday >= $dailyCountThreshold;
    }

    private function notifyFinanceRefundPending(RefundRequest $refundRequest): void
    {
        $recipients = User::role('Finance')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $currency = $refundRequest->branch?->currency ?? 'TZS';

        (new SystemNotification(
            "Refund of {$refundRequest->amount} {$currency} on sale {$refundRequest->sale?->sale_no} awaits approval — {$refundRequest->initiator?->name}.",
            '/finance/refunds',
            category: 'finance',
            target_role: 'Finance',
            type: 'refund_pending',
        ))->deliver($recipients);
    }

    private function notifyFinanceVoidFlagged(Sale $sale): void
    {
        $recipients = User::role('Finance')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $currency = $sale->branch?->currency ?? 'TZS';

        (new SystemNotification(
            "Flagged void on sale {$sale->sale_no} ({$sale->amount_paid} {$currency}) by {$sale->voidedBy?->name} — review required.",
            '/finance/voids',
            category: 'finance',
            target_role: 'Finance',
            type: 'void_flagged',
        ))->deliver($recipients);
    }

    private function activeShift(User $user): ?Shift
    {
        return $user->branch
            ? Shift::open()->where('branch_id', $user->branch_id)->first()
            : null;
    }
}
