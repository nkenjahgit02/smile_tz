<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Audit log of every order status transition (user, timestamp, reason).
 *
 * @property int $branch_order_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $user_id
 * @property string|null $reason
 */
#[Fillable([
    'branch_order_id', 'from_status', 'to_status', 'user_id', 'reason',
    'deleted_at', 'deleted_by', 'deletion_reason',
])]
class OrderStatusHistory extends Model
{
    use SoftDeletes;

    protected $table = 'order_status_history';

    public function order(): BelongsTo
    {
        return $this->belongsTo(BranchOrder::class, 'branch_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
