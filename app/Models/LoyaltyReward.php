<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $points_cost
 * @property string $type
 * @property float $value
 * @property string $min_tier
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LoyaltyReward extends Model
{
    protected $fillable = [
        'name', 'points_cost', 'type', 'value', 'min_tier', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Currently redeemable rewards.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Monetary value of the reward when redeemed against a subtotal.
     */
    public function valueFor(float $subtotal): float
    {
        return match ($this->type) {
            'percentage' => round($subtotal * $this->value / 100, 2),
            default => round((float) $this->value, 2),
        };
    }
}
