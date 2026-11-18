<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\PumpGenerationQueue;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Services\Credits\CreditService;
use App\Services\Pricing\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies a Pixelforge generation object (from a POST response, a webhook or a
 * poll) to our row. The single place where status and money change.
 *
 * Rules (from the docs):
 *  - only move forward: queued → processing → succeeded|failed; never back from a final state
 *  - a duplicate or late event is a no-op
 *  - succeeded: charge = cost(usage × the generation's own rates) + markup; release the rest of the hold
 *  - failed: free, release the whole hold
 */
class GenerationUpdater
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CreditService $credits,
    ) {}

    /**
     * @param  array<string, mixed>  $object  Pixelforge generation object
     * @return bool whether anything changed
     */
    public function apply(Generation $generation, array $object): bool
    {
        $next = GenerationStatus::tryFrom((string) ($object['status'] ?? ''));

        if (! $next) {
            return false;
        }

        $changed = DB::transaction(function () use ($generation, $object, $next) {
            /** @var Generation $row fresh, locked copy: decisions are made on committed state */
            $row = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            // Link the provider id even if the status doesn't move (e.g. webhook beat the POST).
            if ($row->provider_id === null && isset($object['id'])) {
                $row->provider_id = (string) $object['id'];
                $row->submitted_at ??= $this->time($object['created_at'] ?? null) ?? now();
            }

            if (! $row->status->canMoveTo($next)) {
                $row->isDirty() && $row->save();

                return false;
            }

            if (isset($object['rates']) && is_array($object['rates'])) {
                $row->rates = array_map('strval', $object['rates']); // billed at the provider's rates
            }

            $row->status = $next;
            $row->started_at ??= $this->time($object['started_at'] ?? null);
            $row->completed_at = $this->time($object['completed_at'] ?? null) ?? ($next->isFinal() ? now() : null);
            $row->next_attempt_at = null;

            match ($next) {
                GenerationStatus::Succeeded => $this->succeed($row, $object),
                GenerationStatus::Failed => $this->fail($row, (string) data_get($object, 'error.code', 'generation_failed'), (string) data_get($object, 'error.message', 'The generation failed.')),
                default => null,
            };

            $row->save();
            $generation->setRawAttributes($row->getAttributes(), true);

            return true;
        });

        if ($changed && $generation->status->isFinal()) {
            PumpGenerationQueue::dispatch(); // a provider slot just freed up

            if ($generation->status === GenerationStatus::Succeeded) {
                StoreGenerationImage::dispatch($generation->id);
            }
        }

        return $changed;
    }

    /** Fail a generation that never reached Pixelforge (or was rejected). Free. */
    public function failLocally(Generation $generation, string $code, string $message): bool
    {
        return DB::transaction(function () use ($generation, $code, $message) {
            $row = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($row->status->isFinal()) {
                return false;
            }

            $row->status = GenerationStatus::Failed;
            $row->completed_at = now();
            $row->next_attempt_at = null;
            $this->fail($row, $code, $message);
            $row->save();
            $generation->setRawAttributes($row->getAttributes(), true);

            return true;
        });
    }

    /** @param  array<string, mixed>  $object */
    private function succeed(Generation $row, array $object): void
    {
        $usage = (array) ($object['usage'] ?? []);

        $row->input_text_tokens = (int) ($usage['input_text_tokens'] ?? 0);
        $row->input_image_tokens = (int) ($usage['input_image_tokens'] ?? 0);
        $row->output_tokens = (int) ($usage['output_tokens'] ?? 0);
        $row->reasoning_tokens = (int) ($usage['reasoning_tokens'] ?? 0);
        $row->provider_cost_nano = $this->pricing->providerCost($usage, $row->rates);
        $row->charge_nano = $this->pricing->charge($row->provider_cost_nano);
        $row->image_url = $object['image_url'] ?? null;
        $row->image_url_expires_at = $this->time($object['image_url_expires_at'] ?? null);

        $this->credits->settle($row, $row->charge_nano);
    }

    private function fail(Generation $row, string $code, string $message): void
    {
        $row->error_code = mb_substr($code, 0, 48);
        $row->error_message = mb_substr($message, 0, 500);
        $row->charge_nano = 0;

        $this->credits->refund($row);
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
