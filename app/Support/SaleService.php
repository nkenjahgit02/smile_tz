<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LoyaltyReward;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReceiptCopy;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CustomerAccountService $accounts,
    ) {}

    /**
     * Record a sale: consume FIFO stock, compute per-line profit, apply coupon,
     * record split payments, snapshot an immutable receipt, and optionally open a layaway.
     *
     * @param  array<int, array{product_id: int, product_variant_id?: int|null, quantity: float, price?: float|null, price_override_reason?: string|null, discount?: float, loss_reason?: string|null}>  $items
     * @param  array<int, array{method: string, amount: float, reference?: string|null}>  $payments
     */
    public function checkout(
        int $branchId,
        User $cashier,
        array $items,
        array $payments,
        ?int $customerId = null,
        ?int $shiftId = null,
        ?string $couponCode = null,
        array $options = [],
    ): Sale {
        $customer = $customerId ? Customer::findOrFail($customerId) : null;

        $reductionReason = trim((string) ($options['price_reduction_reason'] ?? ''));

        $assembled = $this->assembleLines($branchId, $cashier, $items);

        $subtotal = round(array_sum(array_map(
            fn ($line) => $line['line_subtotal'],
            $assembled,
        )), 2);

        $coupon = null;
        $couponDiscount = 0.0;
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if (! $coupon || ! $coupon->isValidFor($subtotal)) {
                throw ValidationException::withMessages(['coupon' => 'Coupon code is not valid for this basket.']);
            }
            $couponDiscount = $coupon->discountFor($subtotal);
        }

        $reward = null;
        $rewardDiscount = 0.0;
        if (($options['reward_id'] ?? null) && $customer) {
            $reward = LoyaltyReward::find($options['reward_id']);
            if (! $reward) {
                throw ValidationException::withMessages(['reward' => 'Reward not found.']);
            }
            $this->assertRewardRedeemable($customer, $reward);
            $rewardDiscount = $reward->valueFor($subtotal - $couponDiscount);
        }

        $discountTotal = round(array_sum(array_map(
            fn ($line) => $line['discount'],
            $assembled,
        )) + $couponDiscount + $rewardDiscount, 2);

        $this->assertDiscountWithinPolicy($subtotal, $discountTotal, $cashier);

        $total = round(max($subtotal - $discountTotal, 0), 2);

        $paymentTotal = round(array_sum(array_map(
            fn ($p) => (float) $p['amount'],
            $payments,
        )), 2);

        $layawayDeposit = (float) ($options['layaway_deposit'] ?? 0);

        $required = $layawayDeposit > 0 && $customer ? $layawayDeposit : $total;
        $amountDiff = round(abs(round($paymentTotal, 2) - round($required, 2)), 2);
        $hasReduction = ! ($layawayDeposit > 0 && $customer) && $amountDiff > 0.005;

        if ($hasReduction && ! in_array($reductionReason, ['discount', 'loss', 'overpayment'], true)) {
            throw ValidationException::withMessages([
                'price_reduction_reason' => 'A reason is required when the amount received differs from the sale total.',
            ]);
        }

        $this->validatePayments($payments, $layawayDeposit, $total, $customer, $hasReduction);

        $cashPaymentTotal = round(array_sum(array_map(
            fn ($p) => $p['method'] === 'cash' ? (float) $p['amount'] : 0,
            $payments,
        )), 2);

        $changeAmount = $cashPaymentTotal > $total ? round($cashPaymentTotal - $total, 2) : 0;
        $amountPaid = round(min($paymentTotal, $total), 2);

        $saleNo = 'SALE-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        $sale = DB::transaction(function () use (
            $branchId,
            $cashier,
            $customer,
            $shiftId,
            $saleNo,
            $assembled,
            $subtotal,
            $discountTotal,
            $total,
            $paymentTotal,
            $changeAmount,
            $amountPaid,
            $payments,
            $hasReduction,
            $amountDiff,
            $reductionReason,
        ) {
            /** @var Sale $sale */
            $sale = Sale::create([
                'branch_id' => $branchId,
                'user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'shift_id' => $shiftId,
                'sale_no' => $saleNo,
                'receipt_no' => $saleNo,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0,
                'total' => $total,
                'amount_paid' => $amountPaid,
                'change_amount' => $changeAmount,
                'status' => 'active',
                'payment_status' => $paymentTotal >= $total ? 'paid' : 'partial',
                'has_price_reduction' => $hasReduction,
                'price_reduction_amount' => $amountDiff,
                'price_reduction_reason' => $hasReduction ? $reductionReason : null,
                'idempotency_key' => $options['idempotency_key'] ?? null,
            ]);

            foreach ($assembled as $i => $line) {
                $cogs = $line['require_stock']
                    ? $this->stock->consumeFifo(
                        $branchId,
                        $line['product_id'],
                        $line['product_variant_id'],
                        $line['quantity'],
                        reason: "sale {$saleNo}",
                    )
                    : 0;

                $profit = round($line['line_subtotal'] - $cogs, 2);
                $isLoss = $profit < 0;

                if ($isLoss && empty($line['loss_reason'])) {
                    throw ValidationException::withMessages([
                        "items.{$i}.loss_reason" => 'A loss reason is required when selling below cost.',
                    ]);
                }

                $sale->items()->create([
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                    'original_unit_price' => $line['original_unit_price'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'line_total' => $line['line_subtotal'],
                    'cost_price' => $cogs,
                    'profit' => $profit,
                    'is_loss' => $isLoss,
                    'loss_reason' => $isLoss ? $line['loss_reason'] : null,
                    'price_overridden' => $line['price_overridden'],
                    'price_override_reason' => $line['price_override_reason'],
                    'price_override_approved_by' => $line['price_override_approved_by'],
                    'is_price_reduced' => $line['price_reduced'],
                ]);
            }

            foreach ($payments as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'received_by' => $cashier->id,
                ]);

                if ($customer) {
                    if ($payment['method'] === 'credit') {
                        $this->accounts->extendCredit($customer, (float) $payment['amount'], $branchId, reference: $sale->sale_no, referenceId: $sale->id, actedBy: $cashier);
                    } elseif ($payment['method'] === 'deposit') {
                        $this->accounts->spendDeposit($customer, (float) $payment['amount'], $branchId, description: "Applied to sale {$sale->sale_no}", actedBy: $cashier);
                    }
                }
            }

            return $sale;
        });

        if ($customer) {
            $customer->increment('lifetime_spend', $total);
            $customer->update([
                'loyalty_tier' => $this->accounts->tierFor((int) $customer->lifetime_points, (float) $customer->lifetime_spend),
            ]);
        }

        if ($customer && $reward) {
            $this->accounts->redeem($customer, $reward, $subtotal - $couponDiscount, $branchId, reference: $sale->sale_no, actedBy: $cashier);
        }

        if ($coupon) {
            $sale->coupons()->attach($coupon->id, ['discount_amount' => $couponDiscount]);
            $coupon->increment('used_count');
        }

        if ($layawayDeposit > 0 && $customer) {
            $this->openLayaway($sale, $customer, $layawayDeposit, $cashier);
        }

        $this->accounts->earnPoints($sale, $cashier);

        $this->snapshotReceipt($sale, $cashier);

        if ($hasReduction) {
            $this->notifyAdminsOfReduction($sale, $amountDiff, $reductionReason);
        }

        return $sale;
    }

    /**
     * Validate a reward can be redeemed without mutating points (used pre-sale so a
     * failed checkout never burns points).
     */
    private function assertRewardRedeemable(Customer $customer, LoyaltyReward $reward): void
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => 'This reward is not active.']);
        }
        $tierRank = array_flip(['bronze', 'silver', 'gold', 'platinum']);
        if ($tierRank[$reward->min_tier] > $tierRank[$customer->loyalty_tier]) {
            throw ValidationException::withMessages(['reward' => "Reward requires {$reward->min_tier} tier or higher."]);
        }
        if ((int) $customer->loyalty_points < (int) $reward->points_cost) {
            throw ValidationException::withMessages(['reward' => 'Not enough loyalty points.']);
        }
    }

    /**
     * Open a layaway for a sale that was partially paid.
     */
    public function openLayaway(Sale $sale, Customer $customer, float $deposit, User $cashier): Layaway
    {
        $balance = round(max($sale->total - $deposit, 0), 2);

        /** @var Layaway $layaway */
        $layaway = Layaway::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'branch_id' => $sale->branch_id,
            'total_amount' => $sale->total,
            'deposit_amount' => $deposit,
            'paid_amount' => $deposit,
            'balance' => $balance,
            'status' => $balance <= 0 ? 'fulfilled' : 'active',
            'created_by' => $cashier->id,
        ]);

        $layaway->payments()->create([
            'amount' => $deposit,
            'method' => 'cash',
            'received_by' => $cashier->id,
        ]);

        return $layaway;
    }

    /**
     * Record an installment payment against a layaway.
     */
    public function payInstallment(Layaway $layaway, float $amount, string $method, ?string $reference, User $cashier): Layaway
    {
        if ($layaway->status !== 'active') {
            throw ValidationException::withMessages(['layaway' => 'This layaway is not active.']);
        }

        DB::transaction(function () use ($layaway, $amount, $method, $reference, $cashier) {
            $layaway->increment('paid_amount', $amount);
            $layaway->decrement('balance', $amount);

            $layaway->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'received_by' => $cashier->id,
            ]);

            if ($layaway->balance <= 0) {
                $layaway->update(['status' => 'fulfilled']);
            }
        });

        return $layaway->fresh();
    }

    /**
     * Void a sale and restore FIFO stock.
     */
    public function void(Sale $sale, User $user, string $reason): void
    {
        if ($sale->status !== 'active') {
            throw ValidationException::withMessages(['sale' => 'Only active sales can be voided.']);
        }
        if ($sale->layaway) {
            throw ValidationException::withMessages(['sale' => 'Cancelled finalise the layaway before voiding the sale.']);
        }

        DB::transaction(function () use ($sale, $user, $reason) {
            foreach ($sale->items as $item) {
                $this->stock->restock(
                    $sale->branch_id,
                    $item->product_id,
                    $item->product_variant_id,
                    (float) $item->quantity,
                    unitCost: (float) $item->cost_price,
                    batchNumber: 'RETURN-'.$sale->sale_no,
                );
            }

            $sale->update([
                'status' => 'voided',
                'voided_by' => $user->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);
        });
    }

    /**
     * Execute an approved refund: return the money by voiding the sale and
     * restoring the units sold, or just adjusting status when `$restock` is off.
     */
    public function refund(Sale $sale, User $user, string $reason, bool $restock = true): void
    {
        if ($sale->status !== 'active') {
            throw ValidationException::withMessages(['sale' => 'Only active sales can be refunded.']);
        }

        DB::transaction(function () use ($sale, $user, $reason, $restock) {
            if ($restock) {
                foreach ($sale->items as $item) {
                    $this->stock->restock(
                        $sale->branch_id,
                        $item->product_id,
                        $item->product_variant_id,
                        (float) $item->quantity,
                        unitCost: (float) $item->cost_price,
                        batchNumber: 'RETURN-'.$sale->sale_no,
                    );
                }
            }

            $sale->update([
                'status' => 'returned',
                'voided_by' => $user->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);
        });
    }

    /**
     * Enforce the Finance discount policy on the backend: a cashier without
     * `sales.price_override` may not apply more than the configured maximum
     * discount (percent, optionally also an absolute amount).
     */
    private function assertDiscountWithinPolicy(float $subtotal, float $discountTotal, User $cashier): void
    {
        if ($cashier->can('sales.price_override')) {
            return;
        }

        $maxPercent = (float) Setting::number('finance.discount_max_percent', 5);
        $maxAmount = (float) Setting::number('finance.discount_max_amount', 0);

        $allowed = $subtotal * ($maxPercent / 100);
        if ($maxAmount > 0) {
            $allowed = min($allowed, $maxAmount);
        }

        if ($discountTotal > $allowed + 0.005) {
            throw ValidationException::withMessages([
                'items' => "Discount exceeds the cashier policy limit of {$allowed} ({$maxPercent}% max). Manager approval is required.",
            ]);
        }
    }

    /**
     * Build and validate cart line items from the frontend payload.
     */
    private function assembleLines(int $branchId, User $cashier, array $items): array
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'A sale must contain at least one item.']);
        }

        $canOverride = $cashier->can('sales.price_override');
        $out = [];

        foreach ($items as $i => $item) {
            $product = Product::where('is_active', true)->find($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(["items.{$i}.product_id" => 'Product not found or inactive.']);
            }

            $variantId = isset($item['product_variant_id']) && $item['product_variant_id']
                ? (int) $item['product_variant_id']
                : null;

            if ($variantId) {
                $variant = ProductVariant::where('product_id', $product->id)->find($variantId);
                if (! $variant) {
                    throw ValidationException::withMessages(["items.{$i}.product_variant_id" => 'Variant not found.']);
                }
            }

            $quantity = (float) ($item['quantity'] ?? 1);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$i}.quantity" => 'Quantity must be greater than zero.']);
            }

            $variantPrice = $variantId ? (float) (ProductVariant::find($variantId)->price ?? 0) : null;

            $catalogPrice = $variantPrice !== null && $variantPrice > 0
                ? $variantPrice
                : (float) $product->shop_price;

            $requestedPrice = isset($item['price']) && $item['price'] !== null && $item['price'] !== ''
                ? (float) $item['price']
                : $catalogPrice;

            $priceOverridden = abs($requestedPrice - $catalogPrice) > 0.005;
            $isMarkup = $priceOverridden && $requestedPrice > $catalogPrice;
            $overrideReason = trim((string) ($item['price_override_reason'] ?? ''));

            if ($priceOverridden) {
                if ($isMarkup && ! $canOverride) {
                    throw ValidationException::withMessages([
                        "items.{$i}.price" => 'Price override is not allowed for this user.',
                    ]);
                }
                if ($isMarkup && $overrideReason === '') {
                    throw ValidationException::withMessages([
                        "items.{$i}.price_override_reason" => 'A price override reason is required.',
                    ]);
                }
            }

            $discount = max((float) ($item['discount'] ?? 0), 0);
            $discount = min($discount, $requestedPrice * $quantity);

            $lineSubtotal = round($requestedPrice * $quantity - $discount, 2);
            $lineReduction = round(max($catalogPrice * $quantity - $lineSubtotal, 0), 2);

            $out[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'original_unit_price' => $catalogPrice,
                'unit_price' => $requestedPrice,
                'discount' => $discount,
                'line_subtotal' => $lineSubtotal,
                'line_reduction' => $lineReduction,
                'price_reduced' => $lineReduction > 0.005,
                'require_stock' => true,
                'loss_reason' => isset($item['loss_reason']) && $item['loss_reason'] ? trim($item['loss_reason']) : null,
                'price_overridden' => $priceOverridden,
                'price_override_reason' => $priceOverridden ? ($overrideReason ?: null) : null,
                'price_override_approved_by' => $priceOverridden ? $cashier->id : null,
            ];
        }

        return $out;
    }

    /**
     * Validate split payments cover the total (or the layaway deposit), and that
     * deposit / credit methods respect the customer's ledger balances.
     */
    private function validatePayments(array $payments, float $layawayDeposit, float $total, ?Customer $customer = null, bool $allowShort = false): void
    {
        if (empty($payments)) {
            throw ValidationException::withMessages(['payments' => 'At least one payment is required.']);
        }

        $allowed = ['cash', 'card', 'mobile', 'bank_transfer', 'loyalty', 'credit', 'deposit'];
        $covered = 0.0;

        foreach ($payments as $p) {
            if (! in_array($p['method'], $allowed, true)) {
                throw ValidationException::withMessages(['payments' => 'Invalid payment method.']);
            }
            if ((float) $p['amount'] <= 0) {
                throw ValidationException::withMessages(['payments' => 'Payment amounts must be positive.']);
            }

            if (in_array($p['method'], ['credit', 'deposit'], true) && ! $customer) {
                throw ValidationException::withMessages(['payments' => 'A customer is required to use '.$p['method'].' payment.']);
            }

            if ($p['method'] === 'deposit' && $customer && (float) $customer->deposit_balance < (float) $p['amount']) {
                throw ValidationException::withMessages(['payments' => 'Deposit balance is insufficient for this payment.']);
            }

            if ($p['method'] === 'credit') {
                if ($customer && $customer->customer_type !== 'credit') {
                    throw ValidationException::withMessages(['payments' => 'Credit payment requires a credit-typed customer.']);
                }
                if ($customer && (float) $customer->credit_balance + (float) $p['amount'] > (float) $customer->credit_limit) {
                    throw ValidationException::withMessages(['payments' => 'Customer credit limit exceeded.']);
                }
            }

            $covered += (float) $p['amount'];
        }

        $required = $layawayDeposit > 0 ? $layawayDeposit : $total;

        if (round($covered, 2) < round($required, 2) && ! $allowShort) {
            throw ValidationException::withMessages([
                'payments' => "Payments must cover the required amount ({$required}).",
            ]);
        }
    }

    /**
     * Snapshot an immutable receipt so reprints never drift from the original.
     */
    public function snapshotReceipt(Sale $sale, User $user): ReceiptCopy
    {
        $last = ReceiptCopy::where('sale_id', $sale->id)->max('copy_no') ?? 0;

        return ReceiptCopy::create([
            'sale_id' => $sale->id,
            'copy_no' => $last + 1,
            'printed_by' => $user->id,
            'content_snapshot' => $this->buildReceiptPayload($sale),
        ]);
    }

    /**
     * Persist a manually-supplied copy (used by reprint so snapshots stay immutable).
     */
    public function snapshotReceiptFromPayload(Sale $sale, User $user, array $payload): ReceiptCopy
    {
        $last = ReceiptCopy::where('sale_id', $sale->id)->max('copy_no') ?? 0;

        return ReceiptCopy::create([
            'sale_id' => $sale->id,
            'copy_no' => $last + 1,
            'printed_by' => $user->id,
            'content_snapshot' => $payload,
        ]);
    }

    /**
     * Build the full, immutable receipt payload (branch, cashier, items, payments,
     * coupon, totals). This is what reprint endpoints and PDFs render from.
     */
    public function buildReceiptPayload(Sale $sale): array
    {
        $sale->loadMissing(['branch', 'user', 'customer', 'items.product', 'items.productVariant', 'payments', 'coupons', 'shift']);

        return collect([
            'sale_no' => $sale->sale_no,
            'receipt_no' => $sale->receipt_no,
            'sold_at' => $sale->created_at?->toIso8601String(),
            'branch' => $sale->branch?->only(['id', 'name', 'code', 'address', 'phone', 'currency']),
            'cashier' => $sale->user?->only(['id', 'name']),
            'shift_no' => $sale->shift?->shift_no,
            'customer' => $sale->customer ? $sale->customer->only(['id', 'name', 'phone']) : null,
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'variant' => $item->product_variant_id ? ($item->productVariant?->name ?? $item->productVariant?->sku) : null,
                'quantity' => $item->quantity,
                'original_unit_price' => $item->original_unit_price,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'line_total' => $item->line_total,
            ])->values(),
            'subtotal' => $sale->subtotal,
            'discount_total' => $sale->discount_total,
            'tax_total' => $sale->tax_total,
            'total' => $sale->total,
            'coupons' => $sale->coupons->map(fn (Coupon $coupon) => [
                'code' => $coupon->code,
                'discount_amount' => $coupon->pivot->discount_amount,
            ])->values(),
            'payments' => $sale->payments->map(fn (Payment $payment) => [
                'method' => $payment->method,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
            ])->values(),
            'amount_paid' => $sale->amount_paid,
            'change_amount' => $sale->change_amount,
            'has_price_reduction' => $sale->has_price_reduction,
            'price_reduction_amount' => $sale->price_reduction_amount,
            'price_reduction_reason' => $sale->price_reduction_reason,
            'settings' => [
                'paper_width_mm' => (int) Setting::number('receipt.paper_width', 80),
                'header_lines' => preg_split('/\R/u', (string) Setting::get('receipt.header_lines', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                'footer_message' => (string) Setting::get('receipt.footer_message', 'Thank you for your purchase!'),
                'auto_print' => Setting::bool('receipt.auto_print', true),
            ],
        ])->all();
    }

    /**
     * Notify finance staff (or anyone with financial visibility) when the amount
     * received differs from the sale total, so management can investigate why a
     * discount, loss or over-payment occurred.
     */
    private function notifyAdminsOfReduction(Sale $sale, float $amount, string $reason): void
    {
        $admins = User::permission('view-finance-reports')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $label = match ($reason) {
            'loss' => 'sold at a loss (long-held stock / spoilage risk)',
            'overpayment' => 'overpayment / excess received',
            default => 'discount given to customer',
        };

        $currency = $sale->branch?->currency ?? 'TZS';
        $seller = $sale->user?->name ?? 'Unknown';

        (new SystemNotification(
            "Payment discrepancy on sale {$sale->sale_no} · {$amount} {$currency} ({$label}) · seller: {$seller}.",
            "/sales/{$sale->id}",
            category: 'finance',
            target_role: 'Finance',
        ))->deliver($admins);
    }
}
