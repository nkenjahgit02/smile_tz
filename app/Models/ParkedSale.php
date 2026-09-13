<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property string $parked_no
 * @property array $cart_json
 * @property string|null $reference
 * @property Carbon $held_at
 * @property Carbon|null $recalled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['branch_id', 'user_id', 'customer_id', 'parked_no', 'cart_json', 'reference', 'held_at', 'recalled_at'])]
class ParkedSale extends Model
{
    protected function casts(): array
    {
        return [
            'cart_json' => 'array',
            'held_at' => 'datetime',
            'recalled_at' => 'datetime',
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
}
