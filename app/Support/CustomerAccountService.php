<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    /** Points earned per 1,000 spendable currency on a fully/partially paid sale. */
    public const POINTS_PER_THOUSAND = 1;

    /** Tiers reached once lifetime points cross these ceilings. */
    public const TIER_THRESHOLDS_LOYALTY_POINTS = [
        'platinum' => 2000,
        'gold' => 1000,
        'silver' => 500,
    ];

    /**
     * Earn loyalty points on a completed sale (based on amount actually paid).
     */
    public function earnPoints(Sale $sale, ?User $actedBy = null): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $customer = $sale->customer;
        $paid = (float) $sale->amount_paid - (float) $sale->change_amount;
        $points = (int) floor(max($paid, 0) / 1000) * self::POINTS_PER_THOUSAND;

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $sale, $points, $actedBy) {
            $customer->increment('loyalty_points', $points);
            $customer->increment('lifetime_points', $points);
            $customer->update([
                'loyalty_tier' => $this->tierFor($customer->lifetime_points + $points, (float) $customer->lifetime_spend),
            ]);

            $this->record($customer, 'loyalty_earn', $sale->branch_id, [
                'description' => "Earned {$points} pts on sale {$sale->sale_no}",
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
                'points_change' => $points,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Redeem points for a loyalty reward, returning the discount value it grants.
     */
    public function redeem(Customer $customer, LoyaltyReward $reward, float $subtotal, int $branchId, ?string $reference = null, ?User $actedBy = null): float
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => 'This reward is not active.']);
        }
        $tierRank = array_flip($this->tierOrder());
        if ($tierRank[$reward->min_tier] > $tierRank[$customer->loyalty_tier]) {
            throw ValidationException::withMessages(['reward' => "Reward requires {$reward->min_tier} tier or higher."]);
        }
        if ($customer->loyalty_points < $reward->points_cost) {
            throw ValidationException::withMessages(['reward' => 'Not enough loyalty points.']);
        }

        $value = $reward->valueFor($subtotal);

        $description = $reference
            ? 'Redeemed '.$reward->name.' for '.$value.' ('.$reference.')'
            : 'Redeemed '.$reward->name.' for '.$value;

        DB::transaction(function () use ($customer, $reward, $branchId, $description, $actedBy) {
            $customer->decrement('loyalty_points', $reward->points_cost);

            $this->record($customer, 'loyalty_redeem', $branchId, [
                'description' => $description,
                'reference_type' => 'loyalty_reward',
                'reference_id' => $reward->id,
                'points_change' => -$reward->points_cost,
            ], actedBy: $actedBy);
        });

        return $value;
    }

    /**
     * Put money on account (customer deposit / wallet).
     */
    public function deposit(Customer $customer, float $amount, int $branchId, ?string $description = null, ?User $actedBy = null): void
    {
        $deposited = DB::transaction(function () use ($customer, $amount, $branchId, $description, $actedBy) {
            $customer->increment('deposit_balance', $amount);

            return $this->record($customer, 'deposit', $branchId, [
                'description' => $description ?? 'Customer deposit',
            ], actedBy: $actedBy);
        });

        // Nothing to do with return value; deposit is idempotent via the single record.
        unset($deposited);
    }

    /**
     * Spend from a customer's deposit balance (used to pay a sale).
     */
    public function spendDeposit(Customer $customer, float $amount, int $branchId, ?string $description = null, ?User $actedBy = null): void
    {
        if ((float) $customer->deposit_balance < $amount) {
            throw ValidationException::withMessages(['deposit' => 'Insufficient deposit balance.']);
        }

        DB::transaction(function () use ($customer, $amount, $branchId, $description, $actedBy) {
            $customer->decrement('deposit_balance', $amount);

            $this->record($customer, 'deposit_reversal', $branchId, [
                'description' => $description ?? 'Deposit applied to sale',
                'amount' => -$amount,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Grant a credit-limit usage on a sale (AR receivable increases).
     */
    public function extendCredit(Customer $customer, float $amount, int $branchId, ?string $reference = null, ?int $referenceId = null, ?User $actedBy = null): void
    {
        if ($customer->customer_type !== 'credit') {
            throw ValidationException::withMessages(['credit' => 'Credit is only available to credit-typed customers.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['credit' => 'Credit amount must be positive.']);
        }
        if ((float) $customer->credit_balance + $amount > (float) $customer->credit_limit) {
            throw ValidationException::withMessages(['credit' => 'Customer credit limit exceeded.']);
        }

        DB::transaction(function () use ($customer, $amount, $branchId, $reference, $referenceId, $actedBy) {
            $customer->increment('credit_balance', $amount);

            $this->record($customer, 'credit_sale', $branchId, [
                'description' => $reference ? "Credit sale {$reference}" : 'Credit sale',
                'reference_type' => $referenceId ? 'sale' : null,
                'reference_id' => $referenceId,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Receive a payment against a customer's credit balance (AR decreases).
     */
    public function collectCredit(Customer $customer, float $amount, int $branchId, string $method = 'cash', ?string $description = null, ?User $actedBy = null): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['credit' => 'Amount must be positive.']);
        }
        if ((float) $customer->credit_balance < $amount) {
            throw ValidationException::withMessages(['credit' => 'Amount exceeds the current credit balance.']);
        }

        DB::transaction(function () use ($customer, $amount, $branchId, $method, $description, $actedBy) {
            $customer->decrement('credit_balance', $amount);

            $this->record($customer, 'credit_payment', $branchId, [
                'description' => $description ?: 'Credit payment received ('.$method.')',
                'amount' => -$amount,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Manually adjust a customer's credit balance (authorised action, e.g. write-off / correction).
     */
    public function adjustCredit(Customer $customer, float $delta, int $branchId, string $reason, ?User $actedBy = null): void
    {
        if ((float) $customer->credit_balance + $delta < 0) {
            throw ValidationException::withMessages(['credit' => 'Adjustment would make the credit balance negative.']);
        }

        DB::transaction(function () use ($customer, $delta, $branchId, $reason, $actedBy) {
            if ($delta >= 0) {
                $customer->increment('credit_balance', $delta);
            } else {
                $customer->decrement('credit_balance', abs($delta));
            }

            $this->record($customer, 'credit_adjust', $branchId, [
                'description' => $reason,
                'amount' => $delta,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Manual points adjustment (e.g. bonus / penalty for promos or disputes).
     */
    public function adjustPoints(Customer $customer, int $delta, int $branchId, string $reason, ?User $actedBy = null): void
    {
        DB::transaction(function () use ($customer, $delta, $branchId, $reason, $actedBy) {
            if ($delta >= 0) {
                $customer->increment('loyalty_points', $delta);
                $customer->increment('lifetime_points', $delta);
            } else {
                $customer->decrement('loyalty_points', abs($delta));
            }

            $customer->update([
                'loyalty_tier' => $this->tierFor($customer->lifetime_points, (float) $customer->lifetime_spend),
            ]);

            $this->record($customer, 'loyalty_adjust', $branchId, [
                'description' => $reason,
                'points_change' => $delta,
            ], actedBy: $actedBy);
        });
    }

    /**
     * Compute the tier from lifetime points (falling back to spend).
     */
    public function tierFor(int $lifetimePoints, float $lifetimeSpend): string
    {
        foreach (self::TIER_THRESHOLDS_LOYALTY_POINTS as $tier => $threshold) {
            if ($lifetimePoints >= $threshold) {
                return $tier;
            }
        }

        return 'bronze';
    }

    /**
     * Write a ledger row with running balances (credit / deposit / points).
     */
    private function record(Customer $customer, string $type, int $branchId, array $data = [], ?User $actedBy = null): CustomerTransaction
    {
        $pointsChange = (int) ($data['points_change'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        unset($data['points_change'], $data['amount']);

        return $customer->transactions()->create(array_merge($data, [
            'branch_id' => $branchId,
            'type' => $type,
            'amount' => $amount,
            'points' => $pointsChange,
            'running_credit' => (float) $customer->credit_balance,
            'running_deposit' => (float) $customer->deposit_balance,
            'running_points' => (int) $customer->loyalty_points,
            'created_by' => $actedBy?->id ?? auth()->id(),
        ]));
    }

    private function tierOrder(): array
    {
        return ['bronze', 'silver', 'gold', 'platinum'];
    }
}
