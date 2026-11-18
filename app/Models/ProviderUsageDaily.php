<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pixelforge's own GET /v1/usage numbers for one key and one UTC day.
 *
 * @property int $id
 * @property int $api_key_id
 * @property string $date Y-m-d (UTC)
 * @property int $generations
 * @property int $input_text_tokens
 * @property int $input_image_tokens
 * @property int $output_tokens
 * @property int $reasoning_tokens
 * @property int $cost_nano
 * @property Carbon $synced_at
 */
#[Fillable([
    'api_key_id', 'date', 'generations', 'input_text_tokens', 'input_image_tokens',
    'output_tokens', 'reasoning_tokens', 'cost_nano', 'synced_at',
])]
class ProviderUsageDaily extends Model
{
    public $timestamps = false;

    protected $table = 'provider_usage_daily';

    protected function casts(): array
    {
        return [
            // 'date' stays a plain 'Y-m-d' string: a date cast would store
            // '… 00:00:00' and break the UNIQUE(api_key_id, date) upsert lookups.
            'cost_nano' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
