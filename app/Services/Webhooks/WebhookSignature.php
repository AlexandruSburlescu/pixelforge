<?php

namespace App\Services\Webhooks;

use App\Models\ApiKey;

/**
 * Pixelforge-Signature: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">
 * keyed with the webhook secret of the API key that created the generation.
 * We don't know which key that is until we trust the body, so every active key's
 * secret is tried (there are only a handful), each compared in constant time.
 */
final class WebhookSignature
{
    /** @return ApiKey|null the key whose secret signed this payload */
    public static function verify(string $header, string $payload, int $toleranceSeconds, ?int $now = null): ?ApiKey
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$name][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];

        if ($timestamp <= 0 || $signatures === [] || abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            return null; // missing, malformed, or a replay of an old delivery
        }

        foreach (ApiKey::where('is_active', true)->get() as $key) {
            $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $key->webhook_secret);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** For tests and the local simulator: build a valid header. */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }
}
