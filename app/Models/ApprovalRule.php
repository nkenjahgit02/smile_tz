<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable multi-level approval threshold (branch order value, etc.).
 *
 * @property int $id
 * @property string $scope
 * @property string|null $name
 * @property float $threshold
 * @property string $unit
 * @property string|null $required_role
 * @property int|null $branch_id
 * @property bool $enabled
 */
#[Fillable([
    'scope', 'name', 'threshold', 'unit', 'required_role', 'branch_id', 'enabled',
])]
class ApprovalRule extends Model
{
    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The first enabled rule matching the scope (and branch, if set).
     */
    public static function forScope(string $scope, ?int $branchId = null): ?self
    {
        return self::query()
            ->where('scope', $scope)
            ->where('enabled', true)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderByDesc('threshold')
            ->first();
    }
}
