<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer nano-USD (1 USD = 1_000_000_000). Pixelforge reports
 * costs as 9-decimal strings, so nano-USD represents every provider amount exactly.
 * Conversions from decimal strings go through bcmath; floats are never used.
 */
final class Money
{
    public const NANO_PER_USD = 1_000_000_000;

    /** "0.046125000" → 46125000. Rejects more than 9 decimals instead of rounding. */
    public static function nanoFromUsd(string $usd): int
    {
        self::assertDecimal($usd);
        $nano = bcmul($usd, (string) self::NANO_PER_USD, 12);
        $whole = bcadd($nano, '0', 0);

        if (bccomp($nano, $whole, 12) !== 0) {
            throw new InvalidArgumentException("USD amount has more than 9 decimals: {$usd}");
        }

        return (int) $whole;
    }

    /** Exact decimal string with 9 decimals: 46125000 → "0.046125000". */
    public static function toUsdString(int $nano): string
    {
        $sign = $nano < 0 ? '-' : '';
        $abs = abs($nano);

        return $sign.intdiv($abs, self::NANO_PER_USD).'.'.str_pad((string) ($abs % self::NANO_PER_USD), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Human display, rounded half-up to $decimals with integer maths:
     * format(46125000) → "$0.0461", format(46125000, 6) → "$0.046125".
     */
    public static function format(int $nano, int $decimals = 4): string
    {
        $decimals = max(0, min(9, $decimals));
        $unit = 10 ** (9 - $decimals);
        $rounded = intdiv(abs($nano) + intdiv($unit, 2), $unit); // units of 10^-decimals USD
        $scale = 10 ** $decimals;
        $fraction = $decimals > 0 ? '.'.str_pad((string) ($rounded % $scale), $decimals, '0', STR_PAD_LEFT) : '';

        return ($nano < 0 && $rounded > 0 ? '-' : '').'$'.number_format(intdiv($rounded, $scale)).$fraction;
    }

    /**
     * Pixelforge rate (USD per 1M tokens, e.g. "12.50") × tokens → exact nano-USD.
     * tokens × rate / 1e6 USD = tokens × rate × 1000 nano-USD.
     */
    public static function tokenCostNano(int $tokens, string $ratePerMillionUsd): int
    {
        self::assertDecimal($ratePerMillionUsd);

        $nano = bcmul(bcmul((string) $tokens, $ratePerMillionUsd, 12), '1000', 12);
        $whole = bcadd($nano, '0', 0);

        if (bccomp($nano, $whole, 12) !== 0) {
            // Only possible if Pixelforge published a rate with >3 decimals; their
            // own 9-decimal costs could not represent it either. Fail loudly.
            throw new InvalidArgumentException("Rate {$ratePerMillionUsd} does not give a whole nano-USD amount.");
        }

        return (int) $whole;
    }

    /** Apply a markup in basis points, rounding UP to the next nano-USD (never undercharge). */
    public static function withMarkup(int $nano, int $markupBps): int
    {
        return intdiv($nano * (10_000 + $markupBps) + 9_999, 10_000);
    }

    private static function assertDecimal(string $value): void
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Not a decimal string: {$value}");
        }
    }
}
