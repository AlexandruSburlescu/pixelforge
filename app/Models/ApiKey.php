<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Pixelforge API key. secret and webhook_secret are encrypted at rest with
 * APP_KEY and hidden from serialization.
 *
 * @property int $id
 * @property string $name
 * @property string $environment
 * @property string $secret
 * @property string $webhook_secret
 * @property string $hint
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 */
#[Fillable(['name', 'environment', 'secret', 'webhook_secret', 'is_active'])]
#[Hidden(['secret', 'webhook_secret'])]
class ApiKey extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $key) {
            if ($key->isDirty('secret')) {
                $key->hint = '…'.substr($key->secret, -4);
            }
        });
    }

    /** The active key for this app's environment (config pixelforge.environment). */
    public static function current(): ?self
    {
        return static::query()
            ->where('environment', config('pixelforge.environment'))
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /** @return HasMany<ProviderUsageDaily, $this> */
    public function usage(): HasMany
    {
        return $this->hasMany(ProviderUsageDaily::class);
    }
}
