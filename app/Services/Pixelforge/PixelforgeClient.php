<?php

namespace App\Services\Pixelforge;

use App\Models\ApiCall;
use App\Models\ApiKey;
use App\Models\ApiLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only class that talks to Pixelforge. Every call is recorded in api_calls
 * (status, error code, timing, rate-limit headers) with bodies in api_logs.
 * The API key never reaches the database logs.
 */
class PixelforgeClient
{
    public function __construct(
        private readonly Http $http,
        private readonly ApiKey $key,
    ) {}

    public function key(): ApiKey
    {
        return $this->key;
    }

    /**
     * POST /v1/generations. Same idempotency key + same params = same generation back.
     *
     * @param  array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array<string, string>}  $params
     * @return array<string, mixed> the generation object
     */
    public function createGeneration(array $params, string $idempotencyKey, ?string $generationId = null, int $attempt = 1): array
    {
        return $this->send('POST', '/generations', $params, $generationId, $idempotencyKey, $attempt);
    }

    /** @return array<string, mixed> */
    public function getGeneration(string $providerId, ?string $generationId = null): array
    {
        return $this->send('GET', '/generations/'.rawurlencode($providerId), generationId: $generationId);
    }

    /** @return array<string, mixed> */
    public function pricing(): array
    {
        return $this->send('GET', '/pricing');
    }

    /** @return array<string, mixed> */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send('GET', '/usage?'.http_build_query(['start_date' => $startDate, 'end_date' => $endDate]));
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $path,
        ?array $body = null,
        ?string $generationId = null,
        ?string $idempotencyKey = null,
        int $attempt = 1,
    ): array {
        $request = $this->http
            ->baseUrl((string) config('pixelforge.base_url'))
            ->withToken($this->key->secret)
            ->acceptJson()
            ->timeout((int) config('pixelforge.http_timeout_seconds'))
            ->connectTimeout(5);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $started = hrtime(true);
        $response = null;
        $error = null;

        try {
            $response = $method === 'POST' ? $request->post($path, $body) : $request->get($path);
        } catch (ConnectionException $e) {
            $error = new PixelforgeException(null, 'network_error', $e->getMessage());
        }

        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
        $error ??= $response && ! $response->successful() ? $this->toException($response) : null;

        $this->record($method, $path, $body, $response, $error, $generationId, $idempotencyKey, $attempt, $durationMs);

        if ($error) {
            throw $error;
        }

        $this->key->forceFill(['last_used_at' => now()])->saveQuietly();

        return (array) $response->json();
    }

    private function toException(Response $response): PixelforgeException
    {
        $error = (array) $response->json('error', []);
        $retryAfter = $response->header('Retry-After');

        return new PixelforgeException(
            status: $response->status(),
            errorCode: (string) ($error['code'] ?? 'http_'.$response->status()),
            message: (string) ($error['message'] ?? $response->reason()),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            param: isset($error['param']) ? (string) $error['param'] : null,
        );
    }

    /** @param  array<string, mixed>|null  $body */
    private function record(
        string $method,
        string $path,
        ?array $body,
        ?Response $response,
        ?PixelforgeException $error,
        ?string $generationId,
        ?string $idempotencyKey,
        int $attempt,
        int $durationMs,
    ): void {
        // Logging must never break the money path.
        try {
            $remaining = $response?->header('X-RateLimit-Remaining');

            $call = ApiCall::create([
                'api_key_id' => $this->key->id,
                'generation_id' => $generationId,
                'method' => $method,
                'endpoint' => strtok($path, '?') ?: $path,
                'idempotency_key' => $idempotencyKey,
                'attempt' => min($attempt, 255),
                'status_code' => $response?->status(),
                'error_code' => $error?->errorCode,
                'retry_after' => $error?->retryAfter,
                'rate_limit_remaining' => is_numeric($remaining) ? (int) $remaining : null,
                'duration_ms' => $durationMs,
            ]);

            ApiLog::create([
                'api_call_id' => $call->id,
                'request_body' => $body !== null
                    ? json_encode($body)
                    : (str_contains($path, '?') ? substr($path, strpos($path, '?') + 1) : null),
                'response_body' => $response ? mb_substr($response->body(), 0, 20_000) : $error?->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not record Pixelforge API call', ['error' => $e->getMessage()]);
        }
    }
}
