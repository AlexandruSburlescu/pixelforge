<?php

namespace Tests\Unit;

use App\Services\Pricing\PricingService;
use App\Services\Pricing\PricingTable;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    /** Verbatim from the Pixelforge docs (GET /v1/pricing). */
    private function table(): PricingTable
    {
        return PricingTable::fromApi(json_decode(<<<'JSON'
        {
          "rates": {"input_text_tokens": "5.00", "input_image_tokens": "10.00", "output_tokens": "40.00", "reasoning_tokens": "12.50"},
          "tokens": {
            "characters_per_text_token": 4, "text_token_variance": 0.15, "per_reference_image": 765,
            "output": {
              "low": {"1024x1024": 280, "1536x1024": 420, "1024x1536": 420},
              "medium": {"1024x1024": 1100, "1536x1024": 1650, "1024x1536": 1650},
              "high": {"1024x1024": 4300, "1536x1024": 6450, "1024x1536": 6450}
            },
            "reasoning": {"low": {"min": 0, "max": 500}, "medium": {"min": 200, "max": 2000}, "high": {"min": 1000, "max": 8000}}
          }
        }
        JSON, true));
    }

    public function test_docs_worked_example_max_cost_is_exact(): void
    {
        // high, 1536x1024, 400-char prompt, 2 reference images, max text + reasoning → $0.373875000
        $cost = (new PricingService(0))->providerCost([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ], $this->table()->rates);

        $this->assertSame('0.373875000', Money::toUsdString($cost));
    }

    public function test_docs_succeeded_example_cost(): void
    {
        $cost = (new PricingService(0))->providerCost(
            ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184, 'total_tokens' => 10411],
            $this->table()->rates,
        );

        // 12×5 + 765×10 + 6450×40 + 3184×12.5 (per 1M) = 0.000060 + 0.007650 + 0.258000 + 0.039800
        $this->assertSame('0.305510000', Money::toUsdString($cost));
    }

    public function test_docs_usage_example_matches(): void
    {
        $cost = (new PricingService(0))->providerCost(
            ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
            $this->table()->rates,
        );

        $this->assertSame('0.169080000', Money::toUsdString($cost));
    }

    public function test_estimate_max_equals_worked_example_plus_markup(): void
    {
        $estimate = (new PricingService(2500))->estimate($this->table(), str_repeat('a', 400), '1536x1024', 'high', 2);

        $this->assertSame(115, $estimate->tokens['input_text_tokens']['max']);
        $this->assertSame(85, $estimate->tokens['input_text_tokens']['min']);
        // 0.373875000 × 1.25 = 0.46734375 exactly
        $this->assertSame('0.467343750', Money::toUsdString($estimate->maxNano));
        $this->assertLessThan($estimate->expectedNano, $estimate->minNano);
        $this->assertLessThan($estimate->maxNano, $estimate->expectedNano);
    }

    public function test_prompt_length_counts_unicode_characters_not_bytes(): void
    {
        $estimate = (new PricingService(0))->estimate($this->table(), 'ăîșțâ', '1024x1024', 'low', 0);

        $this->assertSame(2, $estimate->tokens['input_text_tokens']['expected']); // ceil(5/4)
    }

    public function test_markup_rounds_up_never_down(): void
    {
        $this->assertSame(125, Money::withMarkup(100, 2500));
        $this->assertSame(2, Money::withMarkup(1, 2500));   // 1.25 → 2
        $this->assertSame(0, Money::withMarkup(0, 2500));
    }

    public function test_money_conversions_are_exact(): void
    {
        $this->assertSame(46_125_000, Money::nanoFromUsd('0.046125000'));
        $this->assertSame('0.046125000', Money::toUsdString(46_125_000));
        $this->assertSame('$0.0461', Money::format(46_125_000));
        $this->assertSame('$1,234.50', Money::format(1_234_500_000_000, 2));
        $this->assertSame(5_000, Money::tokenCostNano(1, '5.00'));
        $this->assertSame(12_500, Money::tokenCostNano(1, '12.50'));
    }

    public function test_sub_nano_amounts_are_rejected_not_rounded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::nanoFromUsd('0.0000000001');
    }
}
