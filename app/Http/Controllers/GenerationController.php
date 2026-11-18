<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Http\Presenters\GenerationPresenter;
use App\Http\Requests\StoreGenerationRequest;
use App\Services\Credits\InsufficientCredits;
use App\Services\Generations\GenerationService;
use App\Services\Pixelforge\PricingCatalog;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GenerationController extends Controller
{
    public function create(Request $request, PricingCatalog $catalog): Response
    {
        try {
            $pricing = $catalog->current()->toArray();
        } catch (Throwable) {
            $pricing = null; // page still works; the server prices on submit
        }

        return Inertia::render('Generate', [
            'pricing' => $pricing,
            'markupBps' => (int) config('pixelforge.markup_bps'),
            'recent' => $request->user()->generations()->latest()->limit(6)->get()
                ->map(GenerationPresenter::present(...)),
        ]);
    }

    public function store(StoreGenerationRequest $request, GenerationService $service): RedirectResponse
    {
        try {
            $generation = $service->create($request->user(), $request->safe()->except('idempotency_key'), $request->validated('idempotency_key'));
        } catch (InsufficientCredits $e) {
            return back()->withErrors(['credits' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['prompt' => 'Pricing is unavailable right now, so nothing was submitted. Please try again shortly.'])->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Queued. Up to '.Money::format($generation->hold_nano).' is on hold until it finishes.',
        ]);

        return to_route('generations.create');
    }

    public function index(Request $request): Response
    {
        $page = $request->user()->generations()->latest()->cursorPaginate(24);

        return Inertia::render('History', [
            'generations' => $page->through(GenerationPresenter::present(...)),
        ]);
    }

    /** Lightweight polling endpoint for in-flight rows: GET /generations/status?ids[]=… */
    public function status(Request $request): JsonResponse
    {
        $ids = array_slice(array_filter((array) $request->query('ids', []), 'is_string'), 0, 50);

        $rows = $request->user()->generations()->whereKey($ids)->get();

        return response()->json([
            'generations' => $rows->map(GenerationPresenter::present(...))->values(),
            'balance' => Money::format($request->user()->fresh()->balance_nano),
            'in_flight' => $request->user()->generations()->whereIn('status', GenerationStatus::inFlight())->count(),
        ]);
    }
}
