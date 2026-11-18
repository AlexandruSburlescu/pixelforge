<?php

namespace Tests\Support;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\Money;

trait FakesPixelforge
{
    protected ApiKey $apiKey;

    protected function setUpPixelforge(): void
    {
        $this->apiKey = ApiKey::create([
            'name' => 'test', 'environment' => config('pixelforge.environment'),
            'secret' => 'pf_test_key_abcd', 'webhook_secret' => 'whsec_test_secret', 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> verbatim GET /v1/pricing from the docs */
    protected function pricingBody(): array
    {
        return [
            'object' => 'pricing', 'currency' => 'usd', 'unit' => 'per_1m_tokens',
            'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
            'tokens' => [
                'characters_per_text_token' => 4, 'text_token_variance' => 0.15, 'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => ['low' => ['min' => 0, 'max' => 500], 'medium' => ['min' => 200, 'max' => 2000], 'high' => ['min' => 1000, 'max' => 8000]],
            ],
        ];
    }

    /** @return array<string, mixed> a Pixelforge generation object */
    protected function providerGeneration(string $providerId, string $status, string $generationId, array $overrides = []): array
    {
        $base = [
            'id' => $providerId, 'object' => 'generation', 'status' => $status,
            'prompt' => 'a lighthouse', 'size' => '1024x1024', 'quality' => 'high',
            'reference_images' => [], 'metadata' => ['generation_id' => $generationId],
            'image_url' => null, 'image_url_expires_at' => null, 'usage' => null,
            'rates' => $this->pricingBody()['rates'], 'error' => null,
            'created_at' => '2026-11-18T10:00:00Z', 'started_at' => null, 'completed_at' => null,
        ];

        if ($status === 'succeeded') {
            $base['usage'] = ['input_text_tokens' => 4, 'input_image_tokens' => 0, 'output_tokens' => 4300, 'reasoning_tokens' => 3000, 'total_tokens' => 7304];
            $base['image_url'] = 'https://pixelforge.promptedit.dev/images/'.$providerId.'.png?sig=x';
            $base['image_url_expires_at'] = now()->addDay()->toIso8601String();
            $base['started_at'] = '2026-11-18T10:00:02Z';
            $base['completed_at'] = '2026-11-18T10:00:20Z';
        }

        if ($status === 'failed') {
            $base['error'] = ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'];
            $base['completed_at'] = '2026-11-18T10:00:20Z';
        }

        return array_replace($base, $overrides);
    }

    protected function userWithCredits(string $usd = '5.00'): User
    {
        $user = User::factory()->create();
        app(CreditService::class)->purchase($user, Money::nanoFromUsd($usd), 'test-topup');

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    protected function generationInput(array $overrides = []): array
    {
        return array_replace([
            'prompt' => 'a lighthouse',
            'size' => '1024x1024',
            'quality' => 'high',
            'reference_images' => [],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], $overrides);
    }
}
