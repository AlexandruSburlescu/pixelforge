<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationUpdater;
use App\Services\Pixelforge\PixelforgeClientFactory;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\RequestBudget;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Webhook delivery isn't guaranteed. Anything Pixelforge has but that hasn't moved
 * for a few minutes is polled with GET /v1/generations/{id} and applied through
 * the same GenerationUpdater, so polling and webhooks can never disagree.
 * Runs every minute from the scheduler.
 */
class ReconcileGenerations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public const STALE_AFTER_MINUTES = 3;

    public const BATCH = 20;

    public function handle(PixelforgeClientFactory $clients, GenerationUpdater $updater): void
    {
        $stale = Generation::with('apiKey')
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->orderBy('updated_at')
            ->limit(self::BATCH)
            ->get();

        foreach ($stale as $generation) {
            if (! RequestBudget::take()) {
                return; // next run continues
            }

            try {
                $object = $clients->make($generation->apiKey)->getGeneration($generation->provider_id, $generation->id);
            } catch (PixelforgeException $e) {
                if ($e->errorCode === 'not_found') {
                    // Pixelforge doesn't know it (or it belongs to another key): it never ran, so it's free.
                    $updater->failLocally($generation, 'not_found', 'Pixelforge has no record of this generation. Nothing was charged.');
                }

                continue;
            }

            if (! $updater->apply($generation, $object)) {
                $generation->touch(); // still running: look again in a few minutes, not every minute
            }
        }
    }
}
