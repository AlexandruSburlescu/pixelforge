<?php

namespace App\Jobs;

use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pixelforge image URLs expire 24h after completion. Keep our own copy so the
 * history stays complete. Best effort: failing here never touches money.
 */
class StoreGenerationImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    private const MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(public readonly string $generationId) {}

    public function handle(): void
    {
        $generation = Generation::find($this->generationId);

        if (! $generation || $generation->image_path || ! $generation->image_url) {
            return;
        }

        if ($generation->image_url_expires_at?->isPast()) {
            return; // too late, the URL no longer works
        }

        $response = Http::timeout(30)->get($generation->image_url)->throw();

        if (! str_starts_with((string) $response->header('Content-Type'), 'image/png') || strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException('Unexpected image response for '.$generation->id);
        }

        $path = 'generations/'.$generation->id.'.png';
        Storage::disk('public')->put($path, $response->body());
        $generation->forceFill(['image_path' => $path])->save();
    }
}
