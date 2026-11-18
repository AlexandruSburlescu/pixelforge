<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $api_key_id
 * @property string|null $generation_id
 * @property string $method
 * @property string $endpoint
 * @property string|null $idempotency_key
 * @property int $attempt
 * @property int|null $status_code
 * @property string|null $error_code
 * @property int|null $retry_after
 * @property int|null $rate_limit_remaining
 * @property int $duration_ms
 * @property Carbon $created_at
 */
#[Fillable([
    'api_key_id', 'generation_id', 'method', 'endpoint', 'idempotency_key', 'attempt',
    'status_code', 'error_code', 'retry_after', 'rate_limit_remaining', 'duration_ms',
])]
class ApiCall extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** @return HasOne<ApiLog, $this> */
    public function log(): HasOne
    {
        return $this->hasOne(ApiLog::class);
    }
}
