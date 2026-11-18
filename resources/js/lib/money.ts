/**
 * Money in the browser mirrors App\Support\Money: integer nano-USD (BigInt),
 * never floats. 1 USD = 1_000_000_000 nano-USD.
 */
export const NANO_PER_USD = 1_000_000_000n;

/** Round half-up to `decimals` places and format: 46125000n → "$0.0461". */
export function formatNano(nano: bigint, decimals = 4): string {
    const d = BigInt(Math.max(0, Math.min(9, decimals)));
    const unit = 10n ** (9n - d);
    const abs = nano < 0n ? -nano : nano;
    const rounded = (abs + unit / 2n) / unit;
    const scale = 10n ** d;
    const whole = (rounded / scale).toLocaleString('en-US');
    const fraction =
        d > 0n ? '.' + (rounded % scale).toString().padStart(Number(d), '0') : '';

    return `${nano < 0n && rounded > 0n ? '-' : ''}$${whole}${fraction}`;
}

/** "12.50" (USD per 1M tokens) → 12500n nano-USD per token, exactly. */
export function ratePerTokenNano(ratePerMillion: string): bigint {
    const [whole, fraction = ''] = ratePerMillion.split('.');

    if (fraction.length > 3) {
        // Not representable in whole nano-USD; the server would reject it too.
        throw new Error(`Unsupported rate precision: ${ratePerMillion}`);
    }

    return BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0') || '0');
}

/** Provider cost + markup (basis points), rounded up like the server. */
export function withMarkup(nano: bigint, markupBps: number): bigint {
    return (nano * BigInt(10_000 + markupBps) + 9_999n) / 10_000n;
}
