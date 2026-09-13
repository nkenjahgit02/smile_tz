<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int $branch_id
 * @property int $godown_id
 * @property string $priority
 * @property Carbon|null $required_date
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property int|null $second_approved_by
 * @property Carbon|null $approved_at
 * @property bool $requires_second_approval
 * @property Carbon|null $picking_started_at
 * @property Carbon|null $picking_completed_at
 * @property string|null $dispatch_note_no
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $received_at
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'order_number', 'branch_id', 'godown_id', 'priority', 'required_date',
    'status', 'notes', 'created_by', 'approved_by', 'second_approved_by',
    'approved_at', 'second_approved_at', 'requires_second_approval',
    'picker_id', 'picking_started_at', 'picking_completed_at',
    'dispatch_note_no', 'dispatcher_id', 'dispatched_at',
    'received_by', 'received_at', 'closed_by', 'closed_at',
    'cancelled_by', 'cancellation_reason', 'deleted_at', 'deleted_by', 'deletion_reason',
])]
class BranchOrder extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'submitted', 'pending_approval', 'approved', 'partially_approved',
        'rejected', 'pending_fulfillment', 'picking', 'ready_for_dispatch',
        'dispatched', 'partially_received', 'received', 'discrepancy_review',
        'closed', 'cancelled',
    ];

    public const PIPELINE_STAGES = [
        'pending_approval', 'picking', 'ready_for_dispatch', 'dispatched', 'discrepancy_review',
    ];

    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'requires_second_approval' => 'boolean',
            'approved_at' => 'datetime',
            'second_approved_at' => 'datetime',
            'picking_started_at' => 'datetime',
            'picking_completed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'godown_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BranchOrderItem::class)->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('branch_id', $locationId)->orWhere('godown_id', $locationId));
    }

    public function value(): float
    {
        return round((float) $this->items->sum(fn ($i) => (float) $i->qty_requested * (float) ($i->unit_cost ?? 0)), 2);
    }

    public function describableStage(): string
    {
        return str_replace('_', ' ', $this->status);
    }
}
