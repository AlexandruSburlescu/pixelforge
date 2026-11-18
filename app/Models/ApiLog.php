<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request/response bodies of an ApiCall. Pruned after
 * config('pixelforge.log_retention_days'); the ApiCall row itself is kept.
 *
 * @property int $id
 * @property int $api_call_id
 * @property string|null $request_body
 * @property string|null $response_body
 */
#[Fillable(['api_call_id', 'request_body', 'response_body'])]
class ApiLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ApiCall, $this> */
    public function apiCall(): BelongsTo
    {
        return $this->belongsTo(ApiCall::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('pixelforge.log_retention_days', 14)));
    }
}
