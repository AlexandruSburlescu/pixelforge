<?php

namespace App\Http\Controllers;

use App\Services\Pixelforge\PricingCatalog;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Landing page. Prices shown here come from the same live Pixelforge rates and
 * the same PricingService as checkout, so the page can never advertise a number
 * the app wouldn't charge.
 */
class WelcomeController extends Controller
{
    public function __invoke(PricingCatalog $catalog, PricingService $pricing): Response
    {
        return Inertia::render('Welcome', [
            'canRegister' => Route::has('register'),
            'pricing' => $this->pricing($catalog, $pricing),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function pricing(PricingCatalog $catalog, PricingService $pricing): ?array
    {
        try {
            $table = $catalog->current();
        } catch (Throwable) {
            return null; // page still renders; the pricing section is hidden
        }

        // A typical ~80 character prompt, square, no reference images.
        $prompt = str_repeat('x', 80);
        $tiers = [];
        $mediumMax = 0;

        foreach (['low', 'medium', 'high'] as $quality) {
            $e = $pricing->estimate($table, $prompt, '1024x1024', $quality, 0);
            $mediumMax = $quality === 'medium' ? $e->maxNano : $mediumMax;
            $tiers[] = [
                'quality' => $quality,
                'min' => Money::format($e->minNano, 4),
                'max' => Money::format($e->maxNano, 4),
                'minNano' => $e->minNano,
                'maxNano' => $e->maxNano,
            ];
        }

        // Where each range sits on a shared scale (0–100), for the range bars.
        $ceiling = max(1, max(array_column($tiers, 'maxNano')));
        $tiers = array_map(fn (array $t) => [
            'quality' => $t['quality'],
            'min' => $t['min'],
            'max' => $t['max'],
            'from' => round($t['minNano'] / $ceiling * 100, 1),
            'to' => round($t['maxNano'] / $ceiling * 100, 1),
        ], $tiers);

        $welcomeNano = Money::nanoFromUsd((string) config('pixelforge.welcome_credit_usd'));

        // The finished generation from the Pixelforge docs, billed the way we bill.
        $usage = ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184];
        $lines = [];

        foreach ($usage as $type => $tokens) {
            $lines[] = [
                'label' => match ($type) {
                    'input_text_tokens' => 'Prompt text',
                    'input_image_tokens' => 'Reference image',
                    'output_tokens' => 'Image output',
                    'reasoning_tokens' => 'Model reasoning',
                },
                'tokens' => number_format($tokens),
                'rate' => '$'.$table->rates[$type].' / 1M',
                'cost' => Money::toUsdString(Money::tokenCostNano($tokens, $table->rates[$type])),
            ];
        }

        $cost = $pricing->providerCost($usage, $table->rates);
        $charge = $pricing->charge($cost);

        return [
            'tiers' => $tiers,
            'markup' => (int) config('pixelforge.markup_bps') / 100,
            'receipt' => [
                'lines' => $lines,
                'cost' => Money::toUsdString($cost),
                'markup' => Money::toUsdString($charge - $cost),
                'charge' => Money::toUsdString($charge),
            ],
            'welcome' => Money::format($welcomeNano, 2),
            // Worst case (every image at its maximum), so the promise always holds.
            'welcomeImages' => $mediumMax > 0 ? intdiv($welcomeNano, $mediumMax) : 0,
        ];
    }
}
