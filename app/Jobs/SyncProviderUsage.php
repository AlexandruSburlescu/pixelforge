<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\ProviderUsageDaily;
use App\Services\Pixelforge\PixelforgeClientFactory;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copies Pixelforge's GET /v1/usage (the source of truth for what Pixelforge
 * charged us) into provider_usage_daily, per key and UTC day, so the admin report
 * can compare it line by line with our own records.
 */
class SyncProviderUsage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $days = 3) {}

    public function handle(PixelforgeClientFactory $clients): void
    {
        $end = now('UTC')->toDateString();
        $start = now('UTC')->subDays(max(1, min(92, $this->days)) - 1)->toDateString();

        $keys = ApiKey::where('environment', config('pixelforge.environment'))
            ->where(fn ($q) => $q->where('is_active', true)->orWhereHas('generations'))
            ->get();

        foreach ($keys as $key) {
            $usage = $clients->make($key)->usage($start, $end);

            foreach ((array) ($usage['data'] ?? []) as $day) {
                ProviderUsageDaily::updateOrCreate(
                    ['api_key_id' => $key->id, 'date' => $day['date']],
                    [
                        'generations' => (int) $day['generations'],
                        'input_text_tokens' => (int) $day['input_text_tokens'],
                        'input_image_tokens' => (int) $day['input_image_tokens'],
                        'output_tokens' => (int) $day['output_tokens'],
                        'reasoning_tokens' => (int) $day['reasoning_tokens'],
                        'cost_nano' => Money::nanoFromUsd((string) $day['cost_usd']),
                        'synced_at' => now(),
                    ],
                );
            }
        }
    }
}
