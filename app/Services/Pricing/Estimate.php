<?php

namespace App\Services\Pricing;

/**
 * What a generation may cost the user (markup included), in nano-USD.
 * max is what we hold from the balance; the real charge is never above it.
 */
final readonly class Estimate
{
    public function __construct(
        public int $minNano,
        public int $expectedNano,
        public int $maxNano,
        /** @var array<string, array{min: int, expected: int, max: int}> */
        public array $tokens,
        /** @var array<string, string> rates used (USD per 1M tokens) */
        public array $rates,
    ) {}
}
