<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\PumpGenerationQueue;
use App\Models\ApiKey;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Pixelforge\PricingCatalog;
use App\Services\Pricing\PricingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Accepting a request is local and fast: price it, hold the maximum charge, store
 * it as pending, wake the pump. Talking to Pixelforge happens in the pump, so a
 * burst of requests never waits on the provider.
 */
class GenerationService
{
    public function __construct(
        private readonly PricingCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly CreditService $credits,
    ) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: list<string>}  $input
     */
    public function create(User $user, array $input, string $idempotencyKey): Generation
    {
        // Replayed form post / double click: hand back the original, change nothing.
        if ($existing = $this->find($user, $idempotencyKey)) {
            return $existing;
        }

        $references = array_values($input['reference_images'] ?? []);
        $table = $this->catalog->current();
        $estimate = $this->pricing->estimate($table, $input['prompt'], $input['size'], $input['quality'], count($references));

        try {
            $generation = DB::transaction(function () use ($user, $input, $references, $idempotencyKey, $estimate) {
                $generation = Generation::create([
                    'user_id' => $user->id,
                    'api_key_id' => ApiKey::current()?->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => GenerationStatus::Pending,
                    'prompt' => $input['prompt'],
                    'size' => $input['size'],
                    'quality' => $input['quality'],
                    'reference_images' => $references,
                    'rates' => $estimate->rates,
                    'estimate_min_nano' => $estimate->minNano,
                    'estimate_nano' => $estimate->expectedNano,
                    'estimate_max_nano' => $estimate->maxNano,
                    'hold_nano' => $estimate->maxNano,
                ]);

                $this->credits->hold($user, $generation, $estimate->maxNano);

                return $generation;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical requests raced; the other one won. Same answer as a replay.
            return $this->find($user, $idempotencyKey);
        }

        PumpGenerationQueue::dispatch();

        return $generation;
    }

    private function find(User $user, string $idempotencyKey): ?Generation
    {
        return Generation::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
    }
}
