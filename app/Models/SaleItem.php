<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property float $quantity
 * @property float $original_unit_price
 * @property float $unit_price
 * @property float $discount
 * @property float $line_total
 * @property float $cost_price
 * @property float $profit
 * @property bool $is_loss
 * @property string|null $loss_reason
 * @property bool $price_overridden
 * @property string|null $price_override_reason
 * @property int|null $price_override_approved_by
 * @property bool $is_price_reduced
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'sale_id', 'product_id', 'product_variant_id', 'quantity',
    'original_unit_price', 'unit_price', 'discount', 'line_total',
    'cost_price', 'profit', 'is_loss', 'loss_reason',
    'price_overridden', 'price_override_reason', 'price_override_approved_by',
    'is_price_reduced',
])]
class SaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'original_unit_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'profit' => 'decimal:2',
            'is_loss' => 'boolean',
            'price_overridden' => 'boolean',
            'is_price_reduced' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
