<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $branch_id
 * @property float $quantity
 * @property float $reserved
 * @property float $in_transit_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'product_variant_id', 'branch_id', 'quantity', 'reserved', 'in_transit_quantity'])]
class Stock extends Model
{
    protected $table = 'stock';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved' => 'decimal:3',
            'in_transit_quantity' => 'decimal:3',
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

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Fetch a Stock row for a product/variant in a branch, creating an empty one if needed.
     */
    public static function forLocation(int $branchId, int $productId, ?int $variantId = null): self
    {
        return self::firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
            ],
            ['quantity' => 0, 'reserved' => 0, 'in_transit_quantity' => 0],
        );
    }

    /**
     * Quantity available to sell or ship: system balance, minus reserved units,
     * minus outstanding outbound transports when this location is the source.
     * Expected inbound deliveries (branch side of an in-transit order) do NOT
     * reduce availability.
     */
    public function available(): float
    {
        $outbound = $this->branch->isGodown() ? (float) $this->in_transit_quantity : 0;

        return round((float) $this->quantity - (float) $this->reserved - $outbound, 3);
    }
}
