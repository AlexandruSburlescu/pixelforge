<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

/**
 * The body of GET /v1/pricing as a typed object. Rates are kept as the provider's
 * decimal strings (USD per 1M tokens) and are only ever used through bcmath.
 */
final readonly class PricingTable
{
    public const TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /**
     * @param  array<string, string>  $rates  token type → USD per 1M tokens
     * @param  array<string, array<string, int>>  $output  quality → size → tokens
     * @param  array<string, array{min: int, max: int}>  $reasoning  quality → range
     */
    public function __construct(
        public array $rates,
        public int $charactersPerTextToken,
        public float $textTokenVariance,
        public int $tokensPerReferenceImage,
        public array $output,
        public array $reasoning,
    ) {
        foreach (self::TOKEN_TYPES as $type) {
            if (! isset($rates[$type])) {
                throw new InvalidArgumentException("Pricing is missing the {$type} rate.");
            }
        }
    }

    /** @param  array<string, mixed>  $body  the JSON body of GET /v1/pricing */
    public static function fromApi(array $body): self
    {
        $tokens = $body['tokens'] ?? [];

        return new self(
            rates: array_map('strval', $body['rates'] ?? []),
            charactersPerTextToken: (int) ($tokens['characters_per_text_token'] ?? 4),
            textTokenVariance: (float) ($tokens['text_token_variance'] ?? 0.15),
            tokensPerReferenceImage: (int) ($tokens['per_reference_image'] ?? 765),
            output: $tokens['output'] ?? [],
            reasoning: $tokens['reasoning'] ?? [],
        );
    }

    /** @return array<string, mixed> same shape as the API, for caching */
    public function toArray(): array
    {
        return [
            'rates' => $this->rates,
            'tokens' => [
                'characters_per_text_token' => $this->charactersPerTextToken,
                'text_token_variance' => $this->textTokenVariance,
                'per_reference_image' => $this->tokensPerReferenceImage,
                'output' => $this->output,
                'reasoning' => $this->reasoning,
            ],
        ];
    }

    /** @return list<string> */
    public function sizes(): array
    {
        return array_keys(reset($this->output) ?: []);
    }

    /** @return list<string> */
    public function qualities(): array
    {
        return array_keys($this->output);
    }
}
