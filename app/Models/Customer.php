<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $customer_no
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string $customer_type
 * @property bool $is_active
 * @property string|null $notes
 * @property float $credit_limit
 * @property float $credit_balance
 * @property float $deposit_balance
 * @property int $loyalty_points
 * @property int $lifetime_points
 * @property float $lifetime_spend
 * @property string $loyalty_tier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'branch_id', 'customer_no', 'name', 'phone', 'email', 'address',
    'customer_type', 'is_active', 'notes', 'credit_limit', 'credit_balance',
    'deposit_balance', 'loyalty_points', 'lifetime_points', 'lifetime_spend', 'loyalty_tier',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_balance' => 'decimal:2',
            'deposit_balance' => 'decimal:2',
            'lifetime_spend' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function layaways()
    {
        return $this->hasMany(Layaway::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class);
    }

    /**
     * Available credit = the unused portion of the credit limit.
     */
    public function getAvailableCreditAttribute(): float
    {
        return round(max((float) $this->credit_limit - (float) $this->credit_balance, 0), 2);
    }
}
