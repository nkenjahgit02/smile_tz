<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $branch_order_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property float $qty_requested
 * @property float|null $qty_approved
 * @property float|null $qty_picked
 * @property float|null $qty_dispatched
 * @property float|null $qty_received
 * @property float|null $qty_damaged
 * @property float|null $qty_shortage
 * @property float|null $unit_cost
 */
#[Fillable([
    'branch_order_id', 'product_id', 'product_variant_id',
    'qty_requested', 'qty_approved', 'qty_picked', 'qty_dispatched',
    'qty_received', 'qty_damaged', 'qty_shortage', 'unit_cost',
])]
class BranchOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:3',
            'qty_approved' => 'decimal:3',
            'qty_picked' => 'decimal:3',
            'qty_dispatched' => 'decimal:3',
            'qty_received' => 'decimal:3',
            'qty_damaged' => 'decimal:3',
            'qty_shortage' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BranchOrder::class, 'branch_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function approvedQuantity(): float
    {
        return (float) ($this->qty_approved ?? $this->qty_requested);
    }
}
