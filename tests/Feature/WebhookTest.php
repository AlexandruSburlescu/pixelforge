<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Enums\LedgerType;
use App\Jobs\StoreGenerationImage;
use App\Models\CreditLedgerEntry;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesPixelforge;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    private Generation $generation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        Bus::fake([StoreGenerationImage::class]);

        // Submission is answered with 503 so the row stays pending with no provider id:
        // exactly the "webhook arrives before our POST returned" situation.
        Http::fake([
            '*/pricing' => Http::response($this->pricingBody()),
            '*/generations' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'busy']], 503, ['Retry-After' => '2']),
        ]);

        $user = $this->userWithCredits('5.00');
        $this->actingAs($user)->post('/generations', $this->generationInput());
        $this->generation = Generation::sole();
    }

    private function deliver(string $eventId, string $type, string $status, ?string $signature = null, ?int $timestamp = null): TestResponse
    {
        $body = json_encode([
            'id' => $eventId, 'object' => 'event', 'type' => $type, 'created_at' => now()->toIso8601String(),
            'data' => ['object' => $this->providerGeneration('gen_PF1', $status, $this->generation->id)],
        ]);

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $signature ?? WebhookSignature::sign($body, 'whsec_test_secret', $timestamp),
            'HTTP_PIXELFORGE_EVENT_ID' => $eventId,
            'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '1',
        ], $body);
    }

    public function test_a_signed_event_updates_the_generation_even_before_our_post_returned(): void
    {
        $this->deliver('evt_1', 'generation.processing', 'processing')->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(GenerationStatus::Processing, $this->generation->status);
        $this->assertSame('gen_PF1', $this->generation->provider_id);
        $this->assertNotNull(WebhookEvent::sole()->processed_at);
    }

    public function test_the_same_event_delivered_twice_is_recorded_and_charged_once(): void
    {
        $this->deliver('evt_2', 'generation.succeeded', 'succeeded')->assertNoContent();
        $this->deliver('evt_2', 'generation.succeeded', 'succeeded')->assertNoContent();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, WebhookEvent::sole()->duplicates);
        $this->assertSame(1, CreditLedgerEntry::where('type', LedgerType::Settle)->count());

        $charge = $this->generation->fresh()->charge_nano;
        $this->assertSame(Money::nanoFromUsd('5.00') - $charge, $this->generation->user->fresh()->balance_nano);
    }

    public function test_a_late_processing_event_never_moves_a_finished_generation_back(): void
    {
        $this->deliver('evt_3', 'generation.succeeded', 'succeeded');
        $this->deliver('evt_4', 'generation.processing', 'processing');

        $this->assertSame(GenerationStatus::Succeeded, $this->generation->fresh()->status);
        $this->assertSame(2, WebhookEvent::whereNotNull('processed_at')->count());
    }

    public function test_failed_event_refunds_in_full(): void
    {
        $this->deliver('evt_5', 'generation.failed', 'failed');

        $this->assertSame(GenerationStatus::Failed, $this->generation->fresh()->status);
        $this->assertSame(Money::nanoFromUsd('5.00'), $this->generation->user->fresh()->balance_nano);
    }

    public function test_bad_or_stale_signatures_are_rejected_and_nothing_is_stored(): void
    {
        $this->deliver('evt_6', 'generation.succeeded', 'succeeded', signature: 't=123,v1=deadbeef')->assertStatus(400);
        $this->deliver('evt_7', 'generation.succeeded', 'succeeded', signature: '')->assertStatus(400);
        $this->deliver('evt_8', 'generation.succeeded', 'succeeded', timestamp: time() - 301)->assertStatus(400);

        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame(GenerationStatus::Pending, $this->generation->fresh()->status);
    }

    public function test_ping_is_accepted(): void
    {
        $body = json_encode(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]]);

        $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => WebhookSignature::sign($body, 'whsec_test_secret'),
        ], $body)->assertNoContent();

        $this->assertNotNull(WebhookEvent::sole()->processed_at);
    }
}
