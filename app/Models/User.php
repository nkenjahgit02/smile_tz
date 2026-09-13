<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property string $name
 * @property string|null $phone
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Branch|null $branch
 */
#[Fillable(['name', 'email', 'password', 'branch_id', 'phone', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    use CausesActivity, HasRoles, LogsActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Locations (branches) this user is explicitly scoped to. Users holding
     * `view-all-locations` ignore this pivot entirely.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_locations')
            ->withTimestamps();
    }

    /**
     * Whether this user can see every location (Owner/Admin, and Finance by
     * default — configurable later via the user_locations pivot instead).
     */
    public function seesAllLocations(): bool
    {
        return $this->can('view-all-locations');
    }

    /**
     * IDs of every location this user is allowed to work with. Falls back to
     * the legacy single branch_id column when no pivot rows exist, so older
     * accounts keep working.
     *
     * @return array<int, int>
     */
    public function scopedLocationIds(): array
    {
        if ($this->seesAllLocations()) {
            return Branch::where('is_active', true)->pluck('id')->map(fn ($v): int => (int) $v)->all();
        }

        $ids = $this->locations()->pluck('branches.id')->map(fn ($v): int => (int) $v)->values()->all();

        if ($ids === []) {
            $ids = $this->branch_id ? [(int) $this->branch_id] : [];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Staff who should see a location-scoped operational notification (e.g. a
     * Tier 1 low-stock alert): anyone with `view-all-locations` who isn't a
     * pure finance user, plus anyone with stock visibility explicitly linked
     * to that location.
     *
     * @return Collection<int, User>
     */
    public static function recipientsForLocation(int $locationId): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Finance'))
            ->where(function ($q) use ($locationId) {
                $q->where(fn ($all) => $all->permission('view-all-locations'))
                    ->orWhere(function ($scoped) use ($locationId) {
                        $scoped->permission('stock.view')
                            ->where(function ($link) use ($locationId) {
                                $link->where('branch_id', $locationId)
                                    ->orWhereHas('locations', fn ($l) => $l->where('branches.id', $locationId));
                            });
                    });
            })
            ->get();
    }

    public function activeRoleName(): string
    {
        return $this->roles()->first()?->name ?? 'None';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'branch_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName) => "User account was {$eventName}");
    }
}
