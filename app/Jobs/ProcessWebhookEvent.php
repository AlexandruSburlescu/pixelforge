<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Generations\GenerationUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

/**
 * Applies a stored webhook event. Safe to run more than once: processed_at is
 * checked first, and GenerationUpdater ignores anything that isn't a step forward.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public readonly string $eventId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->eventId))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(GenerationUpdater $updater): void
    {
        $event = WebhookEvent::where('event_id', $this->eventId)->first();

        if (! $event || $event->processed_at) {
            return;
        }

        $object = (array) data_get($event->payload, 'data.object', []);
        $generation = $this->findGeneration($object);

        if ($generation && str_starts_with($event->type, 'generation.')) {
            $updater->apply($generation, $object);
        }

        // Unknown generation (e.g. another environment's key) or a ping: recorded, nothing to do.
        DB::table('webhook_events')->where('id', $event->id)->update([
            'generation_id' => $generation?->id,
            'processed_at' => now(),
        ]);
    }

    /**
     * Match on our own id in metadata first: a webhook can arrive before our POST
     * returned, when we don't know Pixelforge's id yet. Fall back to the provider id.
     *
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $ourId = data_get($object, 'metadata.generation_id');
        $providerId = $object['id'] ?? null;

        $generation = is_string($ourId) ? Generation::find($ourId) : null;

        if ($generation && $generation->provider_id && $providerId && $generation->provider_id !== $providerId) {
            return null; // metadata points at a row linked to a different provider generation
        }

        return $generation ?? (is_string($providerId) ? Generation::where('provider_id', $providerId)->first() : null);
    }
}
