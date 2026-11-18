<?php

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * All cost maths in one place. Provider cost = Σ tokens × rate (exact nano-USD).
 * User charge = provider cost + markup, rounded up to the next nano-USD.
 */
final class PricingService
{
    public function __construct(private readonly int $markupBps) {}

    /**
     * Range of what the user may be charged, before submitting.
     * Unknown until completion: text tokens (±variance) and reasoning tokens (range by quality).
     */
    public function estimate(PricingTable $table, string $prompt, string $size, string $quality, int $referenceImages): Estimate
    {
        $textGuess = (int) ceil(mb_strlen($prompt) / $table->charactersPerTextToken);
        // Variance as an integer percentage keeps the bounds exact: 0.15 → 15.
        $variancePct = (int) round($table->textTokenVariance * 100);
        $textMin = intdiv($textGuess * (100 - $variancePct), 100);
        $textMax = intdiv($textGuess * (100 + $variancePct) + 99, 100); // ceil

        $reasoning = $table->reasoning[$quality] ?? ['min' => 0, 'max' => 0];
        $output = (int) ($table->output[$quality][$size] ?? 0);
        $images = $referenceImages * $table->tokensPerReferenceImage;

        $tokens = [
            'input_text_tokens' => ['min' => $textMin, 'expected' => $textGuess, 'max' => $textMax],
            'input_image_tokens' => ['min' => $images, 'expected' => $images, 'max' => $images],
            'output_tokens' => ['min' => $output, 'expected' => $output, 'max' => $output],
            'reasoning_tokens' => [
                'min' => (int) $reasoning['min'],
                'expected' => intdiv((int) $reasoning['min'] + (int) $reasoning['max'], 2),
                'max' => (int) $reasoning['max'],
            ],
        ];

        $charge = fn (string $bound) => $this->charge($this->providerCost(
            array_map(fn (array $t) => $t[$bound], $tokens),
            $table->rates,
        ));

        return new Estimate($charge('min'), $charge('expected'), $charge('max'), $tokens, $table->rates);
    }

    /**
     * Exact provider cost of finished usage at the generation's own rates.
     *
     * @param  array<string, int>  $usage  token type → count (total_tokens is ignored)
     * @param  array<string, string>  $rates  token type → USD per 1M tokens
     */
    public function providerCost(array $usage, array $rates): int
    {
        $total = 0;

        foreach (PricingTable::TOKEN_TYPES as $type) {
            $total += Money::tokenCostNano((int) ($usage[$type] ?? 0), (string) $rates[$type]);
        }

        return $total;
    }

    /** What the user pays for a provider cost. */
    public function charge(int $providerCostNano): int
    {
        return Money::withMarkup($providerCostNano, $this->markupBps);
    }
}
