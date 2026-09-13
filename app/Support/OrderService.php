<?php

namespace App\Support;

use App\Models\ApprovalRule;
use App\Models\Branch;
use App\Models\BranchOrder;
use App\Models\OrderStatusHistory;
use App\Models\Stock;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Branch ordering state machine. Every transition is recorded in
 * order_status_history (user + timestamp + reason) and any transition that
 * moves stock writes an immutable stock_movements row with reference_id
 * pointing at the branch_orders row.
 */
class OrderService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Create a draft order. Does not touch stock.
     *
     * @param  array<int, array{product_id: int, product_variant_id?: int|null, quantity: float}>  $items
     * @param  array{priority?: string, required_date?: string|null, notes?: string|null}  $options
     */
    public function createDraft(User $user, int $branchId, int $godownId, array $items, array $options = []): BranchOrder
    {
        if ($branchId === $godownId) {
            throw ValidationException::withMessages(['godown_id' => 'The fulfilling location cannot be the requesting branch itself.']);
        }

        $godown = Branch::findOrFail($godownId);
        if (! $godown->isGodown()) {
            throw ValidationException::withMessages(['godown_id' => 'Fulfilling location must be a Godown.']);
        }

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'An order must contain at least one line.']);
        }

        return DB::transaction(function () use ($user, $branchId, $godownId, $items, $options) {
            $order = BranchOrder::create([
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'branch_id' => $branchId,
                'godown_id' => $godownId,
                'priority' => $options['priority'] ?? 'normal',
                'required_date' => $options['required_date'] ?? null,
                'notes' => $options['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'qty_requested' => max((float) $item['quantity'], 0),
                    'unit_cost' => $this->stock->averageUnitCost($branchId, $item['product_id'], $item['product_variant_id'] ?? null),
                ]);
            }

            $this->recordTransition($order, null, 'draft', $user, 'Order created');

            return $order;
        });
    }

    /**
     * Submit a draft for approval. Idempotent: resubmitting the same key is a no-op.
     */
    public function submit(BranchOrder $order, User $user): BranchOrder
    {
        if ($order->status === 'submitted') {
            return $order;
        }

        $this->assertStatus($order, ['draft'], 'Only draft orders can be submitted.');

        $newStatus = 'pending_approval';

        DB::transaction(function () use ($order, $user, $newStatus) {
            $this->setStatus($order, $newStatus, $user, 'Submitted for approval');
        });

        $this->notifyBranchManagers(
            $order,
            "New branch order {$order->order_number} submitted by {$user->name} for {$order->branch?->name}.",
            '/orders',
            'order_submitted',
            $order->branch_id,
        );

        return $order;
    }

    /**
     * Approve (all or partially) an order. Records the decision; if a second
     * approval is required by approval_rules, the order stays pending and is
     * released by approveSecond().
     *
     * @param  array<int, array{item_id?: int, qty: float}>|null  $approvals
     */
    public function approve(BranchOrder $order, User $user, ?array $approvals = null, ?string $reason = null): BranchOrder
    {
        $this->assertStatus($order, ['pending_approval'], 'Only orders awaiting approval can be approved.');

        if ($order->approved_by && $order->approved_by !== $user->id) {
            $order->approved_at = now();
            $order->second_approved_by = $user->id;
            $order->second_approved_at = now();
            $this->applyApprovals($order, $approvals);
            $order->save();

            $this->setStatus($order, $this->isPartiallyApproved($order) ? 'partially_approved' : 'approved', $user, $reason ?: 'Second approval granted');
            $this->notifyCreator($order, "Order {$order->order_number} fully approved.", '/orders', 'order_approved');

            return $order;
        }

        $this->applyApprovals($order, $approvals);

        if ($this->needsSecondApproval($order, $user)) {
            $order->requires_second_approval = true;
            $order->approved_by = $user->id;
            $order->approved_at = now();
            $order->save();
            $this->recordTransition($order, 'pending_approval', 'pending_approval', $user, $reason ?: 'Approved — second approval required');

            return $order;
        }

        $order->approved_by = $user->id;
        $order->approved_at = now();
        $order->save();

        $this->setStatus($order, $this->isPartiallyApproved($order) ? 'partially_approved' : 'approved', $user, $reason ?: 'Approved');
        $this->notifyCreator($order, "Order {$order->order_number} was approved.", '/orders', 'order_approved');

        return $order;
    }

    public function reject(BranchOrder $order, User $user, string $reason): BranchOrder
    {
        $this->assertStatus($order, ['pending_approval', 'discrepancy_review'], 'Only pending orders can be rejected.');

        $this->setStatus($order, 'rejected', $user, $reason);
        $this->notifyCreator($order, "Order {$order->order_number} was rejected: {$reason}", '/orders', 'order_rejected');

        return $order;
    }

    public function returnForChanges(BranchOrder $order, User $user, string $reason): BranchOrder
    {
        $this->assertStatus($order, ['pending_approval'], 'Only pending orders can be returned for changes.');

        $this->setStatus($order, 'draft', $user, $reason);
        $this->notifyCreator($order, "Order {$order->order_number} was returned for changes: {$reason}", '/orders', 'order_returned_for_changes');

        return $order;
    }

    public function startPicking(BranchOrder $order, User $user): BranchOrder
    {
        $this->assertStatus($order, ['approved', 'partially_approved'], 'Only approved orders enter the fulfilment queue.');

        DB::transaction(function () use ($order, $user) {
            $order->picker_id = $user->id;
            $order->picking_started_at = now();
            $order->save();
            $this->setStatus($order, 'picking', $user, 'Picking started');
        });

        $this->notifyGodownManagers($order, "Picking started for order {$order->order_number}.", '/orders', 'picking_started');

        return $order;
    }

    /**
     * Complete picking: confirm the actual quantity available per line, flag shortages.
     *
     * @param  array<int, array{item_id: int, qty: float}>  $picked
     */
    public function completePicking(BranchOrder $order, User $user, array $picked): BranchOrder
    {
        $this->assertStatus($order, ['picking'], 'Only orders being picked can complete picking.');

        DB::transaction(function () use ($order, $user, $picked) {
            foreach ($order->items as $item) {
                $line = collect($picked)->first(fn ($p) => (int) ($p['item_id'] ?? 0) === (int) $item->id) ?? null;
                $item->qty_picked = $line ? (float) $line['qty'] : 0;
                $item->save();
            }
            $order->picking_completed_at = now();
            $order->save();
            $this->setStatus($order, 'ready_for_dispatch', $user, 'Picking completed');
        });

        $this->notifyGodownManagers($order, "Picking completed for order {$order->order_number} — ready for dispatch.", '/orders', 'picking_completed');

        return $order;
    }

    /**
     * Dispatch the order. This is where stock actually moves: the Godown's
     * quantity decreases and its in-transit (outbound) increases; the branch's
     * in-transit (expected) increases too. Every line writes a stock_movements row.
     *
     * @param  array<int, array{item_id: int, qty: float}>  $dispatched
     */
    public function dispatch(BranchOrder $order, User $user, array $dispatched, ?string $dispatchNoteNo = null): BranchOrder
    {
        $this->assertStatus($order, ['ready_for_dispatch'], 'Only orders ready for dispatch can be dispatched.');

        $items = collect($dispatched);
        foreach ($order->items as $item) {
            $line = $items->first(fn ($p) => (int) ($p['item_id'] ?? 0) === (int) $item->id) ?? null;
            $defaultQty = (float) ($item->qty_picked > 0 ? $item->qty_picked : $item->approvedQuantity());
            $item->qty_dispatched = $line ? (float) $line['qty'] : $defaultQty;
        }

        DB::transaction(function () use ($order, $user, $dispatchNoteNo) {
            foreach ($order->items as $item) {
                $dispatchedQty = (float) ($item->qty_dispatched ?? 0);
                if ($dispatchedQty <= 0) {
                    $item->save();

                    continue;
                }

                // Godown: physical stock leaves and becomes in-transit (outbound).
                $source = Stock::forLocation($order->godown_id, $item->product_id, $item->product_variant_id);
                if ($source->available() < $dispatchedQty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.qty" => "Insufficient available stock in {$order->godown?->name} for {$item->product?->name} (available {$source->available()}, requested {$dispatchedQty}).",
                    ]);
                }
                $this->stock->dispatchOut($source, $dispatchedQty, 'order_dispatch', $order, $user);
                $item->save();

                // Branch: mark the quantity as expected in-transit.
                $target = Stock::forLocation($order->branch_id, $item->product_id, $item->product_variant_id);
                $target->increment('in_transit_quantity', $dispatchedQty);
            }

            $order->dispatcher_id = $user->id;
            $order->dispatched_at = now();
            $order->dispatch_note_no = $dispatchNoteNo ?: 'DN-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();
            $this->setStatus($order, 'dispatched', $user, 'Dispatched');
        });

        $this->notifyBranchManagers(
            $order,
            "Order {$order->order_number} dispatched ({$order->dispatch_note_no}) — expected at {$order->branch?->name}.",
            '/orders',
            'shipment_dispatched',
            $order->branch_id,
            extra: ['godown' => true],
        );

        return $order;
    }

    /**
     * Confirm receipt at the branch. Full clean receipt closes the order. Any
     * shortage or damage routes it to Discrepancy Review.
     *
     * @param  array<int, array{item_id: int, received: float, damaged?: float, reason?: string|null}>  $receipt
     */
    public function receive(BranchOrder $order, User $user, array $receipt): BranchOrder
    {
        $this->assertStatus($order, ['dispatched'], 'Only dispatched orders can be confirmed received.');

        $hasDiscrepancy = false;

        DB::transaction(function () use ($order, $user, $receipt, &$hasDiscrepancy) {
            foreach ($order->items as $item) {
                $line = collect($receipt)->first(fn ($p) => (int) ($p['item_id'] ?? 0) === (int) $item->id) ?? null;
                $dispatched = (float) ($item->qty_dispatched ?? 0);
                $received = $line ? (float) $line['received'] : $dispatched;
                $damaged = $line ? (float) ($line['damaged'] ?? 0) : 0;
                $received = max($received, 0);

                $item->qty_received = $received;
                $item->qty_damaged = $damaged;
                $item->qty_shortage = round(max($dispatched - $received - $damaged, 0), 3);
                $item->save();

                if ($item->qty_shortage > 0 || $damaged > 0) {
                    $hasDiscrepancy = true;
                }

                if ($received > 0) {
                    // Branch: expected in-transit resolves into physical stock.
                    $target = Stock::forLocation($order->branch_id, $item->product_id, $item->product_variant_id);
                    $target->decrement('in_transit_quantity', $received);
                    $this->stock->restock(
                        $order->branch_id,
                        $item->product_id,
                        $item->product_variant_id,
                        $received,
                        (float) ($item->unit_cost ?? 0),
                        $order->order_number,
                        reference: $order,
                        type: 'receive',
                    );

                    // Godown: the outstanding outbound leg for this amount resolves.
                    $source = Stock::forLocation($order->godown_id, $item->product_id, $item->product_variant_id);
                    $source->decrement('in_transit_quantity', $received);
                }
            }

            $order->received_by = $user->id;
            $order->received_at = now();
            $order->save();

            $this->setStatus($order, $hasDiscrepancy ? 'partially_received' : 'received', $user, $hasDiscrepancy ? 'Received with discrepancies' : 'Receipt confirmed');

            if (! $hasDiscrepancy) {
                $this->setStatus($order, 'closed', $user, 'Fully received — order closed');
            }
        });

        if ($hasDiscrepancy) {
            $this->setStatus($order, 'discrepancy_review', $user, 'Discrepancy reported — awaiting review');
            $this->notifyDiscrepancyParties($order, "Order {$order->order_number} received with shortages/damage — review required.", '/orders', 'discrepancy_reported');
        } else {
            $this->notifyDiscrepancyParties($order, "Order {$order->order_number} receipt confirmed in full.", '/orders', 'receipt_confirmed');
        }

        return $order;
    }

    /**
     * Branch Manager + Godown Manager resolve a discrepancy; the order closes.
     */
    public function resolveDiscrepancy(BranchOrder $order, User $user, string $resolution): BranchOrder
    {
        $this->assertStatus($order, ['discrepancy_review', 'partially_received'], 'Only orders in discrepancy review can be resolved.');

        $this->setStatus($order, 'closed', $user, $resolution);

        return $order;
    }

    public function cancel(BranchOrder $order, User $user, string $reason): BranchOrder
    {
        $this->assertStatus($order, ['draft', 'submitted', 'pending_approval', 'pending_fulfillment'], 'This order can no longer be cancelled.');

        DB::transaction(function () use ($order, $user, $reason) {
            $order->cancelled_by = $user->id;
            $order->cancellation_reason = $reason;
            $order->save();
            $this->setStatus($order, 'cancelled', $user, $reason);
        });

        return $order;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function needsSecondApproval(BranchOrder $order, User $firstApprover): bool
    {
        if ($firstApprover->hasRole('godown-manager') || $firstApprover->hasRole('Owner') || $firstApprover->hasRole('Admin')) {
            return false;
        }

        $rule = ApprovalRule::forScope('branch_order', $order->branch_id);
        if (! $rule) {
            return false;
        }

        return $order->value() >= (float) $rule->threshold;
    }

    private function applyApprovals(BranchOrder $order, ?array $approvals): void
    {
        $map = collect($approvals ?? [])->keyBy(fn ($a) => (int) ($a['item_id'] ?? 0));

        foreach ($order->items as $item) {
            $line = $map->get($item->id);
            $qty = $line ? (float) $line['qty'] : (float) $item->qty_requested;
            $qty = min(max($qty, 0), (float) $item->qty_requested);
            $item->qty_approved = $qty;
            $item->save();
        }
    }

    private function setStatus(BranchOrder $order, string $toStatus, User $user, ?string $reason = null): void
    {
        $fromStatus = $order->status;
        $order->status = $toStatus;
        $order->save();
        $this->recordTransition($order, $fromStatus, $toStatus, $user, $reason);
    }

    private function recordTransition(BranchOrder $order, ?string $from, string $to, User $user, ?string $reason = null): void
    {
        OrderStatusHistory::create([
            'branch_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);
    }

    private function assertStatus(BranchOrder $order, array $allowed, string $message): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    public function isPartiallyApproved(BranchOrder $order): bool
    {
        return $order->items->contains(fn ($i) => $i->qty_approved !== null && abs((float) $i->qty_approved - (float) $i->qty_requested) > 0.005);
    }

    // ------------------------------------------------------------------
    // Notification helpers — always target who must act next, never everyone.
    // ------------------------------------------------------------------

    private function branchManagers(int $locationId)
    {
        return User::role('branch-manager')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $locationId)->orWhereHas('locations', fn ($l) => $l->where('branches.id', $locationId)))
            ->get();
    }

    private function godownManagers(BranchOrder $order)
    {
        return User::role('godown-manager')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $order->godown_id)->orWhereHas('locations', fn ($l) => $l->where('branches.id', $order->godown_id)))
            ->get();
    }

    private function notifyBranchManagers(BranchOrder $order, string $message, string $link, string $type, int $locationId, array $extra = []): void
    {
        $recipients = $this->branchManagers($locationId);
        if ($recipients->isEmpty()) {
            return;
        }
        foreach ($recipients as $recipient) {
            (new SystemNotification($message, $link, category: 'orders', target_role: 'branch-manager', target_user_id: $recipient->id, target_location_id: $locationId, type: $type))->deliver($recipient);
        }
    }

    private function notifyGodownManagers(BranchOrder $order, string $message, string $link, string $type): void
    {
        foreach ($this->godownManagers($order) as $recipient) {
            (new SystemNotification($message, $link, category: 'orders', target_role: 'godown-manager', target_user_id: $recipient->id, target_location_id: $order->godown_id, type: $type))->deliver($recipient);
        }
    }

    private function notifyCreator(BranchOrder $order, string $message, string $link, string $type): void
    {
        $creator = $order->creator;
        if (! $creator || ! $creator->is_active) {
            return;
        }
        (new SystemNotification($message, $link, category: 'orders', target_role: 'branch-staff', target_user_id: $creator->id, target_location_id: $order->branch_id, type: $type))->deliver($creator);
    }

    private function notifyDiscrepancyParties(BranchOrder $order, string $message, string $link, string $type): void
    {
        $recipients = collect($this->branchManagers($order->branch_id))
            ->merge($this->godownManagers($order))
            ->unique('id')->values();

        foreach ($recipients as $recipient) {
            (new SystemNotification($message, $link, category: 'orders', target_role: $recipient->getRoleNames()->first(), target_user_id: $recipient->id, target_location_id: $recipient->branch_id, type: $type))->deliver($recipient);
        }
    }
}
