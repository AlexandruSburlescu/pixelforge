<?php

namespace App\Services\Reports;

use App\Enums\GenerationStatus;
use App\Models\ApiKey;
use App\Models\Generation;
use App\Models\ProviderUsageDaily;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Operator numbers, all integer nano-USD summed in SQL (exact).
 *  - charged:  what users paid (succeeded generations only; failed ones are free)
 *  - cost:     what Pixelforge charged us, computed from usage × rates
 *  - profit:   charged − cost
 *  - reported: Pixelforge's own GET /v1/usage for the same keys and UTC days
 * A generation is billed on the UTC day it completed, as Pixelforge does.
 */
class ProfitReport
{
    /**
     * @return Collection<int, array{date: string, generations: int, charged: int, cost: int, profit: int, reported_cost: int|null, reported_generations: int|null, matches: bool|null}>
     */
    public function byDay(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $keyIds = $this->keyIds();

        $ours = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereIn('api_key_id', $keyIds)
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date(completed_at) as day, count(*) as generations, sum(charge_nano) as charged, sum(provider_cost_nano) as cost')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $reported = ProviderUsageDaily::query()
            ->whereIn('api_key_id', $keyIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, sum(generations) as generations, sum(cost_nano) as cost')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $days = collect();

        for ($day = $to->copy()->startOfDay(); $day->gte($from->copy()->startOfDay()); $day = $day->subDay()) {
            $d = $day->toDateString();
            $row = $ours->get($d);
            $theirs = $reported->get($d);
            $cost = (int) ($row->cost ?? 0);
            $generations = (int) ($row->generations ?? 0);

            if (! $row && ! $theirs) {
                continue;
            }

            $days->push([
                'date' => $d,
                'generations' => $generations,
                'charged' => (int) ($row->charged ?? 0),
                'cost' => $cost,
                'profit' => (int) ($row->charged ?? 0) - $cost,
                'reported_cost' => $theirs ? (int) $theirs->cost : null,
                'reported_generations' => $theirs ? (int) $theirs->generations : null,
                'matches' => $theirs ? ((int) $theirs->cost === $cost && (int) $theirs->generations === $generations) : null,
            ]);
        }

        return $days;
    }

    /**
     * @return Collection<int, array{user: string, generations: int, failed: int, charged: int, cost: int, profit: int}>
     */
    public function byUser(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Generation::query()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->whereIn('generations.api_key_id', $this->keyIds())
            ->whereIn('generations.status', [GenerationStatus::Succeeded, GenerationStatus::Failed])
            ->whereBetween('generations.completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('users.id', 'users.email')
            ->selectRaw("users.email as user,
                sum(case when generations.status = 'succeeded' then 1 else 0 end) as generations,
                sum(case when generations.status = 'failed' then 1 else 0 end) as failed,
                sum(case when generations.status = 'succeeded' then generations.charge_nano else 0 end) as charged,
                sum(case when generations.status = 'succeeded' then generations.provider_cost_nano else 0 end) as cost")
            ->orderByDesc('charged')
            ->get()
            ->map(fn ($r) => [
                'user' => (string) $r->user,
                'generations' => (int) $r->generations,
                'failed' => (int) $r->failed,
                'charged' => (int) $r->charged,
                'cost' => (int) $r->cost,
                'profit' => (int) $r->charged - (int) $r->cost,
            ]);
    }

    /** Only this environment's keys: other environments report separately. */
    private function keyIds(): Collection
    {
        return ApiKey::where('environment', config('pixelforge.environment'))->pluck('id');
    }
}
