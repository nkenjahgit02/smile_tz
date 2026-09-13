<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $branch_id
 * @property string $type
 * @property float $amount
 * @property int $points
 * @property float $running_credit
 * @property float $running_deposit
 * @property int $running_points
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerTransaction extends Model
{
    protected $fillable = [
        'customer_id', 'branch_id', 'type', 'amount', 'points',
        'running_credit', 'running_deposit', 'running_points',
        'reference_type', 'reference_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'running_credit' => 'decimal:2',
            'running_deposit' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
