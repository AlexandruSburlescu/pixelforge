<?php

namespace Tests\Feature;

use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesPixelforge;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        Bus::fake([StoreGenerationImage::class]);
        Http::fake([
            '*/pricing' => Http::response($this->pricingBody()),
            '*/generations' => fn ($r) => Http::response(
                $this->providerGeneration('gen_'.$r->header('Idempotency-Key')[0], 'queued', $r->header('Idempotency-Key')[0]),
                202,
            ),
        ]);
    }

    public function test_generate_page_gets_live_pricing_and_markup(): void
    {
        $this->actingAs($this->userWithCredits())->get('/generate')->assertInertia(fn (Assert $page) => $page
            ->component('Generate')
            ->where('pricing.rates.output_tokens', '40.00')
            ->where('markupBps', 2500)
            ->has('recent', 0)
            ->where('auth.balance', '$5.00'));
    }

    public function test_history_lists_only_my_generations_with_costs(): void
    {
        $me = $this->userWithCredits();
        $other = $this->userWithCredits();
        $this->actingAs($other)->post('/generations', $this->generationInput());
        $this->actingAs($me)->post('/generations', $this->generationInput(['prompt' => 'mine']));

        $this->actingAs($me)->get('/history')->assertInertia(fn (Assert $page) => $page
            ->component('History')
            ->has('generations.data', 1)
            ->where('generations.data.0.prompt', 'mine')
            ->where('generations.data.0.status', 'queued')
            ->has('generations.data.0.estimate.max'));
    }

    public function test_status_endpoint_only_answers_for_my_generations(): void
    {
        $me = $this->userWithCredits();
        $other = $this->userWithCredits();
        $this->actingAs($other)->post('/generations', $this->generationInput());
        $theirs = Generation::sole()->id;

        $this->actingAs($me)->getJson('/generations/status?ids[]='.$theirs)
            ->assertOk()
            ->assertJsonCount(0, 'generations');
    }

    public function test_credits_page_shows_the_ledger(): void
    {
        $this->actingAs($this->userWithCredits())->get('/credits')->assertInertia(fn (Assert $page) => $page
            ->component('Credits')
            ->has('ledger.data', 1)
            ->where('ledger.data.0.type', 'purchase'));
    }

    public function test_welcome_page_renders(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }
}
