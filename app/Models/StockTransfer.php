<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transfer_no
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property string $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $received_at
 * @property int|null $created_by
 * @property int|null $received_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'transfer_no', 'from_branch_id', 'to_branch_id', 'restock_request_id', 'status',
    'shipped_at', 'received_at', 'created_by', 'received_by', 'notes',
])]
class StockTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'shipped_at' => 'date',
            'received_at' => 'date',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function restockRequest(): BelongsTo
    {
        return $this->belongsTo(RestockRequest::class, 'restock_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
