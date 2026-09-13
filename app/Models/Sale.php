<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property int|null $shift_id
 * @property string $sale_no
 * @property float $subtotal
 * @property float $discount_total
 * @property float $tax_total
 * @property float $total
 * @property float $amount_paid
 * @property float $change_amount
 * @property string $status
 * @property string $payment_status
 * @property string|null $receipt_no
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'branch_id', 'user_id', 'customer_id', 'shift_id', 'sale_no', 'subtotal',
    'discount_total', 'tax_total', 'total', 'amount_paid', 'change_amount',
    'status', 'payment_status', 'receipt_no', 'voided_by', 'void_reason',
    'has_price_reduction', 'price_reduction_amount', 'price_reduction_reason',
    'voided_at', 'is_void_flagged',
])]
class Sale extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'has_price_reduction' => 'boolean',
            'price_reduction_amount' => 'decimal:2',
            'is_void_flagged' => 'boolean',
            'voided_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'sale_coupons')->withPivot('discount_amount');
    }

    public function receiptCopies(): HasMany
    {
        return $this->hasMany(ReceiptCopy::class);
    }

    public function layaway()
    {
        return $this->hasOne(Layaway::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }
}
