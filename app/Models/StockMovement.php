<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immutable-ish ledger row: every stock quantity change must produce exactly
 * one of these. Deleting one requires an explicit permission + a reason.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $branch_id
 * @property string $type
 * @property string $direction
 * @property float $quantity
 * @property float|null $before_quantity
 * @property float|null $after_quantity
 * @property float|null $unit_cost
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $user_id
 * @property string|null $notes
 */
#[Fillable([
    'product_id', 'product_variant_id', 'branch_id', 'type', 'direction',
    'quantity', 'before_quantity', 'after_quantity', 'unit_cost',
    'reference_type', 'reference_id', 'user_id', 'notes',
    'deleted_at', 'deleted_by', 'deletion_reason',
])]
class StockMovement extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'before_quantity' => 'decimal:3',
            'after_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
