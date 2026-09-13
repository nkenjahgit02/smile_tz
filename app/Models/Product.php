<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int|null $brand_id
 * @property int|null $unit_id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property float $godown_price
 * @property float $shop_price
 * @property int $reorder_level
 * @property bool $has_expiry
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'category_id', 'brand_id', 'unit_id', 'name', 'sku', 'barcode',
    'godown_price', 'shop_price', 'reorder_level', 'has_expiry', 'is_active', 'notes',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'godown_price' => 'decimal:2',
            'shop_price' => 'decimal:2',
            'reorder_level' => 'integer',
            'has_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
