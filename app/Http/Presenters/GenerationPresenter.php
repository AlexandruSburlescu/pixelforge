<?php

namespace App\Http\Presenters;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationService;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * What the browser sees for a generation. Money goes out both formatted and as
 * exact 9-decimal strings, never as floats.
 */
final class GenerationPresenter
{
    /**
     * Ids of the given failed generations that already have a retry, in one query
     * (a retry's idempotency key is "retry:<failed id>").
     *
     * @param  iterable<Generation>  $generations
     * @return array<string, true>
     */
    public static function retriedIds(iterable $generations): array
    {
        $keys = collect($generations)
            ->filter(fn (Generation $g) => $g->status === GenerationStatus::Failed)
            ->map(fn (Generation $g) => 'retry:'.$g->id)
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return Generation::whereIn('idempotency_key', $keys)
            ->pluck('idempotency_key')
            ->mapWithKeys(fn (string $key) => [substr($key, 6) => true])
            ->all();
    }

    /**
     * @param  iterable<Generation>  $generations
     * @return list<array<string, mixed>>
     */
    public static function many(iterable $generations): array
    {
        $retried = self::retriedIds($generations);

        return collect($generations)->map(fn (Generation $g) => self::present($g, $retried))->values()->all();
    }

    /**
     * @param  array<string, true>  $retried  from retriedIds()
     * @return array<string, mixed>
     */
    public static function present(Generation $g, array $retried = []): array
    {
        return [
            'id' => $g->id,
            'status' => $g->status->value,
            'waiting_for_slot' => $g->status === GenerationStatus::Pending,
            'retrying' => $g->status === GenerationStatus::Pending && $g->submit_attempts > 0,
            'prompt' => $g->prompt,
            'size' => $g->size,
            'quality' => $g->quality,
            'reference_images' => $g->reference_images,
            'estimate' => [
                'min' => Money::format($g->estimate_min_nano),
                'expected' => Money::format($g->estimate_nano),
                'max' => Money::format($g->estimate_max_nano),
            ],
            'charge' => $g->charge_nano === null ? null : [
                'display' => Money::format($g->charge_nano),
                'exact' => Money::toUsdString($g->charge_nano),
            ],
            'tokens' => $g->status === GenerationStatus::Succeeded ? [
                'input_text' => $g->input_text_tokens,
                'input_image' => $g->input_image_tokens,
                'output' => $g->output_tokens,
                'reasoning' => $g->reasoning_tokens,
            ] : null,
            'image' => self::imageUrl($g),
            'error' => $g->status === GenerationStatus::Failed ? $g->error_message : null,
            'can_retry' => GenerationService::canRetry($g) && ! isset($retried[$g->id]),
            'created_at' => $g->created_at->toIso8601String(),
            'completed_at' => $g->completed_at?->toIso8601String(),
        ];
    }

    private static function imageUrl(Generation $g): ?string
    {
        if ($g->image_path) {
            return Storage::disk('public')->url($g->image_path);
        }

        if ($g->image_url && (! $g->image_url_expires_at || $g->image_url_expires_at->isFuture())) {
            return $g->image_url;
        }

        return null;
    }
}
