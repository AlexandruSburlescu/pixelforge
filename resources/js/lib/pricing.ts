import { ratePerTokenNano, withMarkup } from '@/lib/money';

/** Shape of GET /v1/pricing (rates + token rules), as passed from the server. */
export type PricingTable = {
    rates: Record<TokenType, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
};

export type TokenType =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';

export type Bound = 'min' | 'expected' | 'max';

export type Estimate = {
    charge: Record<Bound, bigint>;
    tokens: Record<TokenType, Record<Bound, number>>;
};

const TYPES: TokenType[] = [
    'input_text_tokens',
    'input_image_tokens',
    'output_tokens',
    'reasoning_tokens',
];

/**
 * Same maths as App\Services\Pricing\PricingService::estimate(), so the number
 * on screen is the number the server holds. The server re-prices on submit.
 */
export function estimate(
    table: PricingTable,
    prompt: string,
    size: string,
    quality: string,
    referenceImages: number,
    markupBps: number,
): Estimate {
    const t = table.tokens;
    const chars = [...prompt.trim()].length; // code points, like mb_strlen
    const guess = Math.ceil(chars / t.characters_per_text_token);
    const pct = Math.round(t.text_token_variance * 100);
    const reasoning = t.reasoning[quality] ?? { min: 0, max: 0 };
    const output = t.output[quality]?.[size] ?? 0;
    const images = referenceImages * t.per_reference_image;

    const tokens: Estimate['tokens'] = {
        input_text_tokens: {
            min: Math.floor((guess * (100 - pct)) / 100),
            expected: guess,
            max: Math.floor((guess * (100 + pct) + 99) / 100),
        },
        input_image_tokens: { min: images, expected: images, max: images },
        output_tokens: { min: output, expected: output, max: output },
        reasoning_tokens: {
            min: reasoning.min,
            expected: Math.floor((reasoning.min + reasoning.max) / 2),
            max: reasoning.max,
        },
    };

    const charge = (bound: Bound): bigint =>
        withMarkup(
            TYPES.reduce(
                (sum, type) =>
                    sum +
                    BigInt(tokens[type][bound]) *
                        ratePerTokenNano(table.rates[type]),
                0n,
            ),
            markupBps,
        );

    return {
        charge: { min: charge('min'), expected: charge('expected'), max: charge('max') },
        tokens,
    };
}
