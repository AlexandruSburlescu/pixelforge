<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $level
 * @property bool $is_admin
 * @property bool $is_system
 */
#[Fillable(['slug', 'name', 'level', 'is_admin'])]
class UserType extends Model
{
    public const USER = 'user';

    public const ADMIN = 'admin';

    public const SUPER_ADMIN = 'super_admin';

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_admin' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Level of a type by slug (unknown slug = unreachable). One query per request. */
    public static function levelOf(string $slug): int
    {
        $levels = once(fn () => static::query()->pluck('level', 'slug')->all());

        return (int) ($levels[$slug] ?? PHP_INT_MAX);
    }
}
