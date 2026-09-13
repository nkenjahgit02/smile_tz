<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property string $shift_no
 * @property float $opening_float
 * @property float|null $closing_count
 * @property float|null $expected_in_drawer
 * @property float|null $difference
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'branch_id', 'user_id', 'shift_no', 'opening_float', 'closing_count',
    'expected_in_drawer', 'difference', 'status', 'opened_at', 'closed_at', 'notes',
    'reviewed_by', 'reviewed_at',
])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'closing_count' => 'decimal:2',
            'expected_in_drawer' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
