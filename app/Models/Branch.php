<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string $currency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'code', 'type', 'address', 'phone', 'email', 'currency', 'is_active'])]
class Branch extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isGodown(): bool
    {
        return $this->type === 'godown';
    }

    public function isStore(): bool
    {
        return $this->type !== 'godown';
    }

    public function scopeGodowns(Builder $query): Builder
    {
        return $query->where('type', 'godown');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
