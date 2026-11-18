<?php

namespace App\Http\Controllers;

use App\Models\CreditLedgerEntry;
use App\Services\Credits\CreditService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fake purchases: no payment provider, credits are simply added. Each click
 * carries a UUID, so a double click or a retried post adds credits once.
 */
class CreditController extends Controller
{
    /** Packs on offer, in whole USD. */
    public const PACKS = [5, 10, 25, 50];

    public function index(Request $request): Response
    {
        $ledger = $request->user()->ledger()->with('generation:id,prompt')->latest('id')->cursorPaginate(30);

        return Inertia::render('Credits', [
            'packs' => self::PACKS,
            'ledger' => $ledger->through(fn (CreditLedgerEntry $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'amount' => Money::format($e->amount_nano, 6),
                'amount_exact' => Money::toUsdString($e->amount_nano),
                'balance_after' => Money::format($e->balance_after_nano, 4),
                'generation' => $e->generation ? ['id' => $e->generation->id, 'prompt' => str($e->generation->prompt)->limit(60)->toString()] : null,
                'note' => $e->note,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function purchase(Request $request, CreditService $credits): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', Rule::in(self::PACKS)],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $credits->purchase(
            $request->user(),
            $data['amount'] * Money::NANO_PER_USD,
            'buy:'.$data['idempotency_key'],
            "Bought \${$data['amount']} of credits (test purchase, no payment taken)",
        );

        return back()->with('flash', ['type' => 'success', 'message' => "Added \${$data['amount']} of credits."]);
    }
}
