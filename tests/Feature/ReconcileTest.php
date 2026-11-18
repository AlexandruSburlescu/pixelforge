<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\ReconcileGenerations;
use App\Jobs\StoreGenerationImage;
use App\Jobs\SyncProviderUsage;
use App\Models\Generation;
use App\Models\ProviderUsageDaily;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesPixelforge;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        Bus::fake([StoreGenerationImage::class]);
    }

    public function test_a_generation_whose_webhooks_never_came_is_finished_by_polling(): void
    {
        $generationId = null;

        Http::fake([
            '*/pricing' => Http::response($this->pricingBody()),
            '*/generations' => function ($request) use (&$generationId) {
                $generationId = $request->header('Idempotency-Key')[0];

                return Http::response($this->providerGeneration('gen_LOST', 'queued', $generationId), 202);
            },
            '*/generations/gen_LOST' => function () use (&$generationId) {
                return Http::response($this->providerGeneration('gen_LOST', 'succeeded', $generationId));
            },
        ]);

        $user = $this->userWithCredits('5.00');
        $this->actingAs($user)->post('/generations', $this->generationInput());

        // Nothing for a while: no webhook ever arrives.
        $this->travel(ReconcileGenerations::staleAfterSeconds() + 60)->seconds();
        ReconcileGenerations::dispatchSync();

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(Money::nanoFromUsd('5.00') - $generation->charge_nano, $user->fresh()->balance_nano);
    }

    public function test_usage_sync_stores_pixelforges_numbers_exactly_and_is_repeatable(): void
    {
        Http::fake(['*/usage*' => Http::response([
            'object' => 'usage',
            'data' => [
                ['date' => '2026-11-17', 'generations' => 3, 'input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'cost_usd' => '0.169080000'],
                ['date' => '2026-11-18', 'generations' => 0, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.000000000'],
            ],
        ])]);

        SyncProviderUsage::dispatchSync(2);
        SyncProviderUsage::dispatchSync(2);

        $this->assertSame(2, ProviderUsageDaily::count());
        $this->assertSame(169_080_000, ProviderUsageDaily::where('date', '2026-11-17')->value('cost_nano'));
    }
}
