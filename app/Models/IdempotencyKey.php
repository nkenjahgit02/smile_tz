<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Records a completed idempotent operation (order submit, sale complete) so a
 * client retry can never double-apply.
 *
 * @property string $scope
 * @property string $key
 * @property int $user_id
 * @property string|null $model_type
 * @property int|null $model_id
 */
#[Fillable(['scope', 'key', 'user_id', 'model_type', 'model_id'])]
class IdempotencyKey extends Model
{
    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
