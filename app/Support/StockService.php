<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Increase stock for a product/variant in a branch, creating a FIFO batch.
     */
    public function restock(
        int $branchId,
        int $productId,
        ?int $variantId,
        float $quantity,
        float $unitCost = 0,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        mixed $reference = null,
        ?string $type = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $stock = Stock::forLocation($branchId, $productId, $variantId);
        $before = (float) $stock->quantity;

        DB::transaction(function () use ($stock, $quantity, $unitCost, $batchNumber, $expiryDate) {
            $stock->increment('quantity', $quantity);

            $stock->batches()->create([
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate ?: null,
                'unit_cost' => $unitCost,
                'quantity' => $quantity,
            ]);
        });

        $this->record(
            $branchId,
            $productId,
            $variantId,
            $type ?? 'purchase',
            'in',
            $quantity,
            $before,
            (float) $stock->fresh()->quantity,
            $unitCost,
            $reference,
            null,
            $batchNumber ? "Restock {$batchNumber}" : 'Restock',
        );
    }

    /**
     * Consume stock FIFO (oldest batches first). Returns the total COGS in currency units.
     */
    public function consumeFifo(
        int $branchId,
        int $productId,
        ?int $variantId,
        float $quantity,
        string $reason = 'sale',
    ): float {
        if ($quantity <= 0) {
            return 0;
        }

        $stock = Stock::forLocation($branchId, $productId, $variantId);
        $before = (float) $stock->quantity;
        $available = $stock->available();

        $negativeAllowed = Setting::bool('system_settings.allow_negative_stock', false);

        if (! $negativeAllowed && $available < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "Insufficient stock for product #{$productId} "
                    .($variantId ? "variant #{$variantId}" : '')
                    ." (available {$available}, requested {$quantity}).",
            ]);
        }

        $cogs = 0;
        $remaining = $quantity;

        DB::transaction(function () use ($stock, $quantity, &$cogs, &$remaining) {
            $batches = $stock->batches()
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                /** @var StockBatch $batch */
                $take = min($batch->quantity, $remaining);
                $batch->decrement('quantity', $take);
                $cogs += $take * $batch->unit_cost;
                $remaining -= $take;
            }

            $stock->decrement('quantity', (float) $quantity);
        });

        $this->notifyLowStockIfCrossed($stock, $available);

        $afterQuantity = (float) $stock->fresh()->quantity;

        $this->record(
            $branchId,
            $productId,
            $variantId,
            'sale',
            'out',
            -$quantity,
            $before,
            $afterQuantity,
            $cogs,
            null,
            null,
            $reason,
        );

        if ($remaining > 0) {
            $target = $variantId ?? $stock->product_id;
            Log::warning("StockService: FIFO shortfall for product {$target} in branch {$stock->branch_id}.", [
                'shortfall' => $remaining,
                'reason' => $reason,
            ]);
        }

        return round($cogs, 2);
    }

    /**
     * Notify staff linked to the location (or with all-location visibility)
     * when stock first crosses at/below its reorder level, so they can flag it
     * without being spammed on every subsequent sale. Sellers are never spammed
     * with alerts for locations they don't work at, and Finance never receives
     * stock-category notifications.
     */
    private function notifyLowStockIfCrossed(Stock $stock, float $previousQuantity): void
    {
        $product = $stock->product;
        $reorder = (int) ($product?->reorder_level ?? 0);
        $after = (float) $stock->quantity;

        if ($product && $after <= $reorder && $previousQuantity > $reorder) {
            $label = $product->name.($stock->variant?->name ? " ({$stock->variant?->name})" : '');

            $recipients = User::recipientsForLocation((int) $stock->branch_id);
            if ($recipients->isEmpty()) {
                return;
            }

            (new SystemNotification(
                "Low stock: {$label} — {$after} left on hand (min {$reorder}) in {$stock->branch?->name}.",
                '/stock?alert_type=low',
                category: 'stock',
                target_location_id: (int) $stock->branch_id,
            ))->deliver($recipients);
        }
    }

    /**
     * Move stock from source to destination branch (with an optional partial quantity per item).
     */
    public function transfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $this->consumeFifo(
                    $transfer->from_branch_id,
                    $item->product_id,
                    $item->product_variant_id,
                    $item->quantity,
                    "transfer #{$transfer->transfer_no}",
                );
            }
        });
    }

    public function receiveTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $qty = $item->received_quantity ?? $item->quantity;

                $costs = $this->averageCostAtShipment($transfer->from_branch_id, $item->product_id, $item->product_variant_id, $qty);

                $this->restock(
                    $transfer->to_branch_id,
                    $item->product_id,
                    $item->product_variant_id,
                    $qty,
                    $costs,
                    "TRF-{$transfer->transfer_no}",
                );
            }

            $transfer->forceFill([
                'status' => 'received',
                'received_at' => now()->toDateString(),
                'received_by' => auth()->id(),
            ])->save();
        });
    }

    /**
     * Compute the average in-value shipped by consuming FIFO from source (no net change: restore after).
     * Costs only — used to carry cost basis across transfers.
     */
    protected function averageCostAtShipment(
        int $branchId,
        int $productId,
        ?int $variantId,
        float $quantity,
    ): float {
        if ($quantity <= 0) {
            return 0;
        }

        $stock = Stock::where([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
        ])->first();

        if (! $stock || $stock->quantity <= 0) {
            return 0;
        }

        $remaining = $quantity;
        $value = 0;
        $taken = 0;

        foreach ($stock->batches()->where('quantity', '>', 0)->orderBy('created_at')->get() as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch->quantity, $remaining);
            $value += $take * $batch->unit_cost;
            $taken += $take;
            $remaining -= $take;
        }

        return $taken > 0 ? round($value / $taken, 2) : 0;
    }

    /**
     * Manual +/- adjustment (damage, wastage, expiry, spoilage, correction).
     * Positive deltas are added as a new batch at the product's current avg cost.
     */
    public function adjust(int $branchId, int $productId, ?int $variantId, float $delta, string $reason): void
    {
        if ($delta == 0) {
            return;
        }

        if ($delta > 0) {
            $this->restock($branchId, $productId, $variantId, $delta, $this->averageUnitCost($branchId, $productId, $variantId), null, null);

            return;
        }

        $this->consumeFifo($branchId, $productId, $variantId, abs($delta), $reason);
    }

    /**
     * Packaged sku lookup helper: match by barcode (product or variant), then sku.
     */
    public static function findByCode(string $code, ?int $branchId = null): ?Product
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($code) {
                $q->where('barcode', $code)->orWhere('sku', $code);
            });

        return $query->first();
    }

    /**
     * Dispatching an order out of the source location: physical quantity leaves
     * and becomes in-transit (outbound). Negative stock is never allowed here.
     */
    public function dispatchOut(Stock $stock, float $quantity, string $type, mixed $reference = null, ?User $user = null): void
    {
        $before = (float) $stock->quantity;
        if ($before < $quantity) {
            throw ValidationException::withMessages(['stock' => "Insufficient stock (on hand {$before}, dispatching {$quantity})."]);
        }

        DB::transaction(function () use ($stock, $quantity) {
            $stock->decrement('quantity', $quantity);
            $stock->increment('in_transit_quantity', $quantity);
        });

        $this->record(
            $stock->branch_id,
            $stock->product_id,
            $stock->product_variant_id,
            $type,
            'out',
            -$quantity,
            $before,
            (float) $stock->fresh()->quantity,
            $this->averageUnitCost($stock->branch_id, $stock->product_id, $stock->product_variant_id),
            $reference,
            $user,
            'Order dispatch',
        );
    }

    /**
     * Write an immutable ledger row for a quantity change. There is no path
     * in the system that changes stock_levels.quantity without a row here.
     */
    public function record(
        int $branchId,
        int $productId,
        ?int $variantId,
        string $type,
        string $direction,
        float $quantity,
        float $before,
        float $after,
        ?float $unitCost = null,
        mixed $reference = null,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return StockMovement::create([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'before_quantity' => $before,
            'after_quantity' => $after,
            'unit_cost' => $unitCost,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'user_id' => $user?->id ?? auth()->id(),
            'notes' => $notes,
        ]);
    }

    public function averageUnitCost(int $branchId, int $productId, ?int $variantId): float
    {
        $stock = Stock::where([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
        ])->first();

        if (! $stock || $stock->quantity <= 0) {
            return 0;
        }

        $value = (float) $stock->batches()->where('quantity', '>', 0)->get()
            ->sum(fn (StockBatch $b) => $b->quantity * $b->unit_cost);

        return round($value / $stock->quantity, 2);
    }
}
