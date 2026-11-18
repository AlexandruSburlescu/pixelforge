<?php

namespace App\Http\Presenters;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * What the browser sees for a generation. Money goes out both formatted and as
 * exact 9-decimal strings, never as floats.
 */
final class GenerationPresenter
{
    /** @return array<string, mixed> */
    public static function present(Generation $g): array
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
