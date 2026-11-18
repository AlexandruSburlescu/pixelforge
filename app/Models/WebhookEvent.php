<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property string|null $provider_generation_id
 * @property string|null $generation_id
 * @property int $delivery_attempt
 * @property int $duplicates
 * @property array<string, mixed> $payload
 * @property Carbon|null $event_created_at
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 */
#[Fillable([
    'event_id', 'type', 'provider_generation_id', 'generation_id', 'delivery_attempt',
    'payload', 'event_created_at', 'processed_at',
])]
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
