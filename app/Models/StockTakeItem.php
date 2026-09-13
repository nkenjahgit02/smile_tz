<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_take_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property float $system_quantity
 * @property float $counted_quantity
 * @property float $difference
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'stock_take_id', 'product_id', 'product_variant_id', 'system_quantity',
    'counted_quantity', 'difference', 'reason',
])]
class StockTakeItem extends Model
{
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    public function take(): BelongsTo
    {
        return $this->belongsTo(StockTake::class, 'stock_take_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
