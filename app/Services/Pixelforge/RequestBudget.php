<?php

namespace App\Services\Pixelforge;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Our own per-minute request budget, shared by submitting and polling, kept a
 * little under Pixelforge's 60/min so we rarely get a 429 at all.
 */
final class RequestBudget
{
    private const KEY = 'pixelforge:requests';

    public static function take(): bool
    {
        return RateLimiter::attempt(self::KEY, (int) config('pixelforge.max_per_minute'), fn () => true, 60);
    }

    public static function secondsUntilAvailable(): int
    {
        return max(1, RateLimiter::availableIn(self::KEY));
    }
}
