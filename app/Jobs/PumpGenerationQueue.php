<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationUpdater;
use App\Services\Pixelforge\PixelforgeClientFactory;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Feeds pending generations to Pixelforge, oldest first, without exceeding its
 * limits (3 in flight, ~60 requests/minute).
 *
 * Woken when a request is accepted, when a generation finishes (slot freed), and
 * every minute by the scheduler as a safety net. One pump runs at a time (cache
 * lock); a wake-up that arrives mid-run is queued, so a freed slot is never missed.
 * Waiting rows cost nothing: there is no per-generation job spinning on retries.
 */
class PumpGenerationQueue implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const MAX_SUBMIT_ATTEMPTS = 6;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function handle(PixelforgeClientFactory $clients, GenerationUpdater $updater): void
    {
        $lock = Cache::lock('pixelforge:pump', 120);

        if (! $lock->get()) {
            return; // another pump is running; it will pick these rows up
        }

        try {
            $this->pump($clients, $updater);
        } finally {
            $lock->release();
        }
    }

    private function pump(PixelforgeClientFactory $clients, GenerationUpdater $updater): void
    {
        $maxInFlight = (int) config('pixelforge.max_concurrent');

        while (true) {
            $free = $maxInFlight - Generation::whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])->count();

            if ($free <= 0) {
                return; // a finishing generation wakes us again
            }

            $batch = Generation::with('apiKey')
                ->where('status', GenerationStatus::Pending)
                ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                ->orderBy('created_at')
                ->limit($free)
                ->get();

            if ($batch->isEmpty()) {
                $this->wakeForBackoff();

                return;
            }

            foreach ($batch as $generation) {
                if (! $this->takeRequestSlot()) {
                    return;
                }

                if (! $this->submit($generation, $clients, $updater)) {
                    return; // provider says it's full: stop and wait for a wake-up
                }
            }
        }
    }

    /** @return bool false when Pixelforge reports it's at capacity */
    private function submit(Generation $generation, PixelforgeClientFactory $clients, GenerationUpdater $updater): bool
    {
        $generation->increment('submit_attempts');

        try {
            $client = $clients->make($generation->apiKey);

            if ($generation->api_key_id === null) {
                $generation->forceFill(['api_key_id' => $client->key()->id])->save();
            }

            $object = $client->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id, 'user_id' => (string) $generation->user_id],
            ], idempotencyKey: $generation->id, generationId: $generation->id, attempt: $generation->submit_attempts);

            $updater->apply($generation, $object);

            return true;
        } catch (PixelforgeException $e) {
            if ($e->isRetryable() && $generation->submit_attempts < self::MAX_SUBMIT_ATTEMPTS) {
                $generation->forceFill(['next_attempt_at' => now()->addMilliseconds($this->backoffMs($generation->submit_attempts, $e->retryAfter))])->save();

                return $e->errorCode !== 'concurrency_limit_exceeded';
            }

            $updater->failLocally($generation, $e->errorCode, $e->userMessage());

            return true;
        } catch (Throwable $e) {
            // Unknown failure (e.g. no API key configured): don't lose the request, back off.
            Log::error('Pixelforge submit failed', ['generation' => $generation->id, 'error' => $e->getMessage()]);

            if ($generation->submit_attempts >= self::MAX_SUBMIT_ATTEMPTS) {
                $updater->failLocally($generation, 'submit_failed', 'We could not send this request to Pixelforge. Nothing was charged.');
            } else {
                $generation->forceFill(['next_attempt_at' => now()->addMilliseconds($this->backoffMs($generation->submit_attempts, null))])->save();
            }

            return true;
        }
    }

    /** Exponential backoff with jitter, never sooner than Retry-After. */
    private function backoffMs(int $attempt, ?int $retryAfterSeconds): int
    {
        $exponential = min(60_000, 1_000 * (2 ** $attempt));

        return max(($retryAfterSeconds ?? 0) * 1_000, $exponential) + random_int(0, 1_000);
    }

    /** Our own per-minute budget, shared with polling, so we rarely see a 429. */
    private function takeRequestSlot(): bool
    {
        $key = 'pixelforge:requests';

        if (RateLimiter::attempt($key, (int) config('pixelforge.max_per_minute'), fn () => true, 60)) {
            return true;
        }

        self::dispatch()->delay(now()->addSeconds(max(1, RateLimiter::availableIn($key))));

        return false;
    }

    /** Some rows are waiting on a backoff timer: wake up when the first one is due. */
    private function wakeForBackoff(): void
    {
        $next = Generation::where('status', GenerationStatus::Pending)->min('next_attempt_at');

        if ($next) {
            self::dispatch()->delay(max(now()->addSecond(), \Carbon\CarbonImmutable::parse($next)));
        }
    }
}
