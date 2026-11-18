<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property int $user_type_id
 * @property int $balance_nano
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserType $userType
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    // user_type_id and balance_nano are deliberately NOT fillable: they only
    // change through admin actions and the credit ledger.

    /** Mirrors the DB defaults so a freshly created model is complete without a refresh. */
    protected $attributes = [
        'user_type_id' => 1,
        'balance_nano' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance_nano' => 'integer',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    /** @return BelongsTo<UserType, $this> */
    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /** @return HasMany<CreditLedgerEntry, $this> */
    public function ledger(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->userType?->is_admin;
    }

    /** True when this user's type is at least as powerful as $slug. */
    public function hasTypeAtLeast(string $slug): bool
    {
        return ($this->userType?->level ?? 0) >= UserType::levelOf($slug);
    }
}
