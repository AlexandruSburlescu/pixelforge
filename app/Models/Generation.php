<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money columns (*_nano) are integer nano-USD: 1 USD = 1_000_000_000.
 *
 * @property string $id
 * @property int $user_id
 * @property int|null $api_key_id
 * @property string $idempotency_key
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property list<string> $reference_images
 * @property array<string, string> $rates
 * @property int $estimate_min_nano
 * @property int $estimate_nano
 * @property int $estimate_max_nano
 * @property int $hold_nano
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $provider_cost_nano
 * @property int|null $charge_nano
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $image_path
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property Carbon|null $submitted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read ApiKey|null $apiKey
 */
#[Fillable([
    'user_id', 'api_key_id', 'idempotency_key', 'status', 'prompt', 'size', 'quality',
    'reference_images', 'rates', 'estimate_min_nano', 'estimate_nano', 'estimate_max_nano', 'hold_nano',
])]
class Generation extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'estimate_min_nano' => 'integer',
            'estimate_nano' => 'integer',
            'estimate_max_nano' => 'integer',
            'hold_nano' => 'integer',
            'provider_cost_nano' => 'integer',
            'charge_nano' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** @return HasMany<CreditLedgerEntry, $this> */
    public function ledger(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    /** @return HasMany<ApiCall, $this> */
    public function apiCalls(): HasMany
    {
        return $this->hasMany(ApiCall::class);
    }

    /** @return HasMany<WebhookEvent, $this> */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
