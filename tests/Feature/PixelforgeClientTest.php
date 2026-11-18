<?php

namespace Tests\Feature;

use App\Models\ApiCall;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Services\Pixelforge\PixelforgeClientFactory;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PixelforgeClientTest extends TestCase
{
    use RefreshDatabase;

    private ApiKey $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = ApiKey::create([
            'name' => 'test', 'environment' => config('pixelforge.environment'),
            'secret' => 'pf_test_secret_1234', 'webhook_secret' => 'whsec_test', 'is_active' => true,
        ]);
    }

    public function test_create_sends_bearer_and_idempotency_key_and_logs_the_call(): void
    {
        Http::fake(['*/generations' => Http::response(['id' => 'gen_1', 'status' => 'queued'], 202, ['X-RateLimit-Remaining' => '58'])]);

        $generation = app(PixelforgeClientFactory::class)->make()->createGeneration(
            ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [], 'metadata' => []],
            'idem-123',
        );

        $this->assertSame('gen_1', $generation['id']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer pf_test_secret_1234')
            && $r->hasHeader('Idempotency-Key', 'idem-123'));

        $call = ApiCall::sole();
        $this->assertSame(202, $call->status_code);
        $this->assertSame(58, $call->rate_limit_remaining);
        $this->assertSame('idem-123', $call->idempotency_key);
        $this->assertStringNotContainsString('pf_test_secret', ApiLog::sole()->request_body.ApiLog::sole()->response_body);
    }

    public function test_errors_become_typed_exceptions_with_retry_after(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many']],
            429, ['Retry-After' => '5'],
        )]);

        try {
            app(PixelforgeClientFactory::class)->make()->getGeneration('gen_1');
            $this->fail('Expected an exception');
        } catch (PixelforgeException $e) {
            $this->assertSame('concurrency_limit_exceeded', $e->errorCode);
            $this->assertSame(5, $e->retryAfter);
            $this->assertTrue($e->isRetryable());
        }

        $this->assertSame('concurrency_limit_exceeded', ApiCall::sole()->error_code);
    }

    public function test_daily_limit_and_content_policy_are_not_retryable(): void
    {
        $this->assertFalse((new PixelforgeException(429, 'daily_limit_exceeded', 'x'))->isRetryable());
        $this->assertFalse((new PixelforgeException(400, 'content_policy_violation', 'x'))->isRetryable());
        $this->assertTrue((new PixelforgeException(503, 'overloaded', 'x'))->isRetryable());
        $this->assertTrue((new PixelforgeException(null, 'network_error', 'x'))->isRetryable());
    }

    public function test_api_key_secrets_are_encrypted_at_rest(): void
    {
        $raw = \DB::table('api_keys')->where('id', $this->key->id)->first();

        $this->assertStringNotContainsString('pf_test_secret', $raw->secret);
        $this->assertStringNotContainsString('whsec_test', $raw->webhook_secret);
        $this->assertSame('…1234', $this->key->hint);
        $this->assertArrayNotHasKey('secret', $this->key->toArray());
    }
}
