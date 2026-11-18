<?php

namespace Tests\Feature;

use App\Jobs\StoreGenerationImage;
use App\Jobs\SyncProviderUsage;
use App\Models\Generation;
use App\Models\User;
use App\Models\UserType;
use App\Services\Generations\GenerationUpdater;
use App\Services\Reports\ProfitReport;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesPixelforge;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        Bus::fake([StoreGenerationImage::class]);
        $this->travelTo('2026-11-18 12:00:00');
    }

    private function succeededGeneration(): Generation
    {
        Http::fake([
            '*/pricing' => Http::response($this->pricingBody()),
            '*/generations' => fn ($r) => Http::response($this->providerGeneration('gen_'.$r->header('Idempotency-Key')[0], 'queued', $r->header('Idempotency-Key')[0]), 202),
        ]);

        $user = $this->userWithCredits();
        $this->actingAs($user)->post('/generations', $this->generationInput());
        $generation = Generation::latest('created_at')->first();
        app(GenerationUpdater::class)->apply($generation, $this->providerGeneration($generation->provider_id, 'succeeded', $generation->id));

        return $generation->fresh();
    }

    public function test_report_matches_pixelforge_usage_to_the_nano_dollar(): void
    {
        $generation = $this->succeededGeneration(); // completed 2026-11-18T10:00:20Z

        Http::fake(['*/usage*' => Http::response(['object' => 'usage', 'data' => [[
            'date' => '2026-11-18', 'generations' => 1, 'input_text_tokens' => 4, 'input_image_tokens' => 0,
            'output_tokens' => 4300, 'reasoning_tokens' => 3000, 'cost_usd' => '0.209520000',
        ]]])]);
        SyncProviderUsage::dispatchSync(1);

        $day = app(ProfitReport::class)->byDay(now()->subDay(), now())->firstWhere('date', '2026-11-18');

        $this->assertSame($generation->provider_cost_nano, $day['cost']);
        $this->assertSame(Money::nanoFromUsd('0.209520000'), $day['reported_cost']);
        $this->assertTrue($day['matches']);
        $this->assertSame($generation->charge_nano - $generation->provider_cost_nano, $day['profit']);

        $user = app(ProfitReport::class)->byUser(now()->subDay(), now())->first();
        $this->assertSame($generation->charge_nano, $user['charged']);

        $cell = app(ProfitReport::class)->byDayAndUser(now()->subDay(), now())->sole();
        $this->assertSame(['2026-11-18', $generation->charge_nano, $generation->provider_cost_nano], [$cell['date'], $cell['charged'], $cell['cost']]);
    }

    public function test_admin_pages_render_for_admins_only(): void
    {
        $this->succeededGeneration();
        $admin = User::factory()->create();
        $admin->user_type_id = UserType::where('slug', UserType::SUPER_ADMIN)->value('id');
        $admin->save();

        foreach ([
            '/admin',
            '/admin/resource/generation-resource/generation-index-page',
            '/admin/resource/credit-ledger-resource/credit-ledger-index-page',
            '/admin/resource/api-call-resource/api-call-index-page',
            '/admin/resource/webhook-event-resource/webhook-event-index-page',
            '/admin/resource/api-key-resource/api-key-index-page',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs(User::factory()->create())->get('/admin/resource/generation-resource/generation-index-page')->assertForbidden();
    }

    public function test_api_key_page_never_shows_secrets(): void
    {
        $admin = User::factory()->create();
        $admin->user_type_id = UserType::where('slug', UserType::SUPER_ADMIN)->value('id');
        $admin->save();

        $this->actingAs($admin)->get('/admin/resource/api-key-resource/api-key-index-page')
            ->assertOk()
            ->assertDontSee('pf_test_key_abcd')
            ->assertDontSee('whsec_test_secret')
            ->assertSee('…abcd');
    }

    public function test_reviewer_command_makes_a_read_only_admin(): void
    {
        $this->artisan('app:create-reviewer', ['email' => 'reviewer@example.com'])->assertSuccessful();

        $reviewer = User::where('email', 'reviewer@example.com')->sole();
        $this->assertSame(UserType::ADMIN, $reviewer->userType->slug);
        $this->actingAs($reviewer)->get('/admin')->assertOk();
        $this->actingAs($reviewer)->get('/admin/resource/api-key-resource/api-key-index-page')->assertForbidden();
    }

    public function test_plain_admins_cannot_open_api_keys(): void
    {
        $admin = User::factory()->create();
        $admin->user_type_id = UserType::where('slug', UserType::ADMIN)->value('id');
        $admin->save();

        $this->actingAs($admin)->get('/admin/resource/api-key-resource/api-key-index-page')->assertForbidden();
    }
}
