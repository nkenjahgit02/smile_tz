<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $branch_id
 * @property string $invoice_no
 * @property float $amount
 * @property float $tax_amount
 * @property float $paid_amount
 * @property string $due_date
 * @property string $status
 * @property string|null $notes
 */
#[Fillable([
    'supplier_id', 'branch_id', 'invoice_no', 'amount', 'tax_amount',
    'paid_amount', 'due_date', 'status', 'notes',
])]
class SupplierInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }
}
