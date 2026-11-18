<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Enums\LedgerType;
use App\Jobs\PumpGenerationQueue;
use App\Jobs\StoreGenerationImage;
use App\Models\CreditLedgerEntry;
use App\Models\Generation;
use App\Services\Generations\GenerationUpdater;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesPixelforge;
use Tests\TestCase;

class GenerationFlowTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        Bus::fake([StoreGenerationImage::class]);
    }

    private function fakeCreateReturns(int $status, array $body, array $headers = []): void
    {
        Http::fake([
            '*/pricing' => Http::response($this->pricingBody()),
            '*/generations' => fn ($request) => Http::response(
                $status === 202 ? $this->providerGeneration('gen_'.substr($request->header('Idempotency-Key')[0], -6), 'queued', $request->header('Idempotency-Key')[0]) : $body,
                $status,
                $headers,
            ),
        ]);
    }

    public function test_submitting_holds_the_max_and_sends_it_to_pixelforge(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('5.00');

        $this->actingAs($user)->post('/generations', $this->generationInput())->assertRedirect('/generate');

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertNotNull($generation->provider_id);
        $this->assertSame(Money::nanoFromUsd('5.00') - $generation->hold_nano, $user->fresh()->balance_nano);
        $this->assertSame($generation->estimate_max_nano, $generation->hold_nano);

        Http::assertSent(fn ($r) => $r->url() === config('pixelforge.base_url').'/generations'
            && $r->header('Idempotency-Key')[0] === $generation->id
            && $r['metadata']['generation_id'] === $generation->id);
    }

    public function test_a_double_submit_creates_one_generation_and_one_hold(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits();
        $input = $this->generationInput();

        $this->actingAs($user)->post('/generations', $input);
        $this->actingAs($user)->post('/generations', $input);

        $this->assertSame(1, Generation::count());
        $this->assertSame(1, CreditLedgerEntry::where('type', LedgerType::Hold)->count());
    }

    public function test_not_enough_credits_is_refused_before_anything_is_created(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('0.01');

        $this->actingAs($user)->post('/generations', $this->generationInput())->assertSessionHasErrors('credits');

        $this->assertSame(0, Generation::count());
        $this->assertSame(Money::nanoFromUsd('0.01'), $user->fresh()->balance_nano);
    }

    public function test_only_three_run_at_once_the_rest_wait_their_turn(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('50.00');

        foreach (range(1, 5) as $_) {
            $this->actingAs($user)->post('/generations', $this->generationInput());
        }

        $this->assertSame(3, Generation::where('status', GenerationStatus::Queued)->count());
        $this->assertSame(2, Generation::where('status', GenerationStatus::Pending)->count());

        // One finishes → a slot frees → the oldest waiting one goes out.
        $first = Generation::where('status', GenerationStatus::Queued)->oldest()->first();
        app(GenerationUpdater::class)->apply($first, $this->providerGeneration($first->provider_id, 'succeeded', $first->id));

        $this->assertSame(3, Generation::where('status', GenerationStatus::Queued)->count());
        $this->assertSame(1, Generation::where('status', GenerationStatus::Pending)->count());
    }

    public function test_overloaded_backs_off_then_gives_up_and_refunds(): void
    {
        $this->fakeCreateReturns(503, ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Busy']], ['Retry-After' => '2']);
        $user = $this->userWithCredits('5.00');

        $this->actingAs($user)->post('/generations', $this->generationInput());

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(1, $generation->submit_attempts);
        $this->assertTrue($generation->next_attempt_at->isFuture());

        for ($i = 2; $i <= PumpGenerationQueue::MAX_SUBMIT_ATTEMPTS; $i++) {
            $this->travel(2)->minutes();
            PumpGenerationQueue::dispatchSync();
        }

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->charge_nano);
        $this->assertSame(Money::nanoFromUsd('5.00'), $user->fresh()->balance_nano);
    }

    public function test_content_policy_rejection_fails_at_once_and_is_free(): void
    {
        $this->fakeCreateReturns(400, ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Rejected']]);
        $user = $this->userWithCredits('5.00');

        $this->actingAs($user)->post('/generations', $this->generationInput(['prompt' => 'x [blocked]']));

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertSame(Money::nanoFromUsd('5.00'), $user->fresh()->balance_nano);
    }

    public function test_success_charges_exact_cost_plus_markup_and_releases_the_rest(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('5.00');
        $this->actingAs($user)->post('/generations', $this->generationInput());
        $generation = Generation::sole();

        app(GenerationUpdater::class)->apply($generation, $this->providerGeneration($generation->provider_id, 'succeeded', $generation->id));

        // 4×5 + 0 + 4300×40 + 3000×12.5 per 1M = 0.000020 + 0.172000 + 0.037500 = 0.209520
        $generation->refresh();
        $this->assertSame('0.209520000', Money::toUsdString($generation->provider_cost_nano));
        $this->assertSame('0.261900000', Money::toUsdString($generation->charge_nano)); // × 1.25
        $this->assertSame(Money::nanoFromUsd('5.00') - $generation->charge_nano, $user->fresh()->balance_nano);
        Bus::assertDispatched(StoreGenerationImage::class);
    }

    public function test_duplicate_and_out_of_order_events_never_double_charge_or_go_backwards(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('5.00');
        $this->actingAs($user)->post('/generations', $this->generationInput());
        $generation = Generation::sole();
        $updater = app(GenerationUpdater::class);

        $succeeded = $this->providerGeneration($generation->provider_id, 'succeeded', $generation->id);
        $this->assertTrue($updater->apply($generation, $succeeded));
        $this->assertFalse($updater->apply($generation, $succeeded));                     // duplicate
        $this->assertFalse($updater->apply($generation, $this->providerGeneration($generation->provider_id, 'processing', $generation->id))); // late
        $this->assertFalse($updater->apply($generation, $this->providerGeneration($generation->provider_id, 'failed', $generation->id)));     // contradictory

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
        $this->assertSame(1, CreditLedgerEntry::where('generation_id', $generation->id)->where('type', LedgerType::Settle)->count());
        $this->assertSame(0, CreditLedgerEntry::where('generation_id', $generation->id)->where('type', LedgerType::Refund)->count());
        $this->assertSame(Money::nanoFromUsd('5.00') - $generation->fresh()->charge_nano, $user->fresh()->balance_nano);
    }

    public function test_failed_generation_is_free(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits('5.00');
        $this->actingAs($user)->post('/generations', $this->generationInput(['prompt' => 'x [fail]']));
        $generation = Generation::sole();

        app(GenerationUpdater::class)->apply($generation, $this->providerGeneration($generation->provider_id, 'failed', $generation->id));

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame(Money::nanoFromUsd('5.00'), $user->fresh()->balance_nano);
    }

    public function test_validation_mirrors_pixelforge(): void
    {
        $this->fakeCreateReturns(202, []);
        $user = $this->userWithCredits();

        $this->actingAs($user)->post('/generations', $this->generationInput([
            'size' => '512x512',
            'quality' => 'ultra',
            'prompt' => str_repeat('a', 4001),
            'reference_images' => ['ftp://x.y/a.png', 'https://a/1', 'https://a/2', 'https://a/3', 'https://a/4'],
        ]))->assertSessionHasErrors(['size', 'quality', 'prompt', 'reference_images', 'reference_images.0']);

        $this->assertSame(0, Generation::count());
    }
}
