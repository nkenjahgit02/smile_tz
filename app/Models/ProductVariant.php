<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property float|null $godown_price
 * @property float|null $shop_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'name', 'sku', 'barcode', 'godown_price', 'shop_price', 'is_active'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'godown_price' => 'decimal:2',
            'shop_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Effective shop price — falls back to the base product.
     */
    public function getPriceAttribute(): float
    {
        return (float) ($this->shop_price ?? $this->product?->shop_price);
    }

    /**
     * Effective godown price — falls back to the base product.
     */
    public function effectiveGodownPrice(): float
    {
        return $this->godown_price ?? $this->product->godown_price;
    }

    /**
     * Effective shop price — falls back to the base product.
     */
    public function effectiveShopPrice(): float
    {
        return $this->shop_price ?? $this->product->shop_price;
    }
}
