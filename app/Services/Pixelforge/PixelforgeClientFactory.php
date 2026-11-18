<?php

namespace App\Services\Pixelforge;

use App\Models\ApiKey;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Builds a client for a given key, or for this environment's active key.
 * A generation is always polled with the key that created it.
 */
class PixelforgeClientFactory
{
    public function __construct(private readonly Http $http) {}

    public function make(?ApiKey $key = null): PixelforgeClient
    {
        $key ??= ApiKey::current();

        if (! $key) {
            throw new RuntimeException(
                'No active Pixelforge API key for environment "'.config('pixelforge.environment').'". Run: php artisan pixelforge:key',
            );
        }

        return new PixelforgeClient($this->http, $key);
    }
}
