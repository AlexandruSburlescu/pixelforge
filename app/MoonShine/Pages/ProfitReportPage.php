<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\ProviderUsageDaily;
use App\Services\Reports\ProfitReport;
use App\Support\Money;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;

/**
 * Credit spend (what users paid), provider cost (what Pixelforge charged us) and
 * profit, per UTC day and per user, with Pixelforge's own GET /v1/usage next to
 * our numbers so any mismatch is visible at a glance.
 */
#[Icon('chart-bar')]
#[Group('Money', 'banknotes')]
#[Order(1)]
class ProfitReportPage extends Page
{
    public function getTitle(): string
    {
        return 'Profit report';
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    /** @return list<ComponentContract> */
    protected function components(): iterable
    {
        $days = max(1, min(92, (int) request('days', 30)));
        $to = now('UTC');
        $from = now('UTC')->subDays($days - 1);
        $report = app(ProfitReport::class);

        $byDay = $report->byDay($from, $to);
        $byUser = $report->byUser($from, $to);

        $charged = $byDay->sum('charged');
        $cost = $byDay->sum('cost');
        $mismatches = $byDay->where('matches', false)->count();
        $unsynced = $byDay->whereNull('matches')->count();
        $lastSync = ProviderUsageDaily::max('synced_at');

        $usd = fn (string $key) => fn (array $row) => Money::format($row[$key], 6);

        return [
            Heading::make("Last {$days} days (UTC), billed on completion day like Pixelforge"),

            Grid::make([
                Column::make([ValueMetric::make('Users were charged')->value(Money::format($charged, 4))], 3),
                Column::make([ValueMetric::make('Pixelforge cost')->value(Money::format($cost, 4))], 3),
                Column::make([ValueMetric::make('Profit')->value(Money::format($charged - $cost, 4))], 3),
                Column::make([ValueMetric::make('Days not matching /v1/usage')->value(
                    $mismatches === 0 ? ($unsynced ? "0 ({$unsynced} not synced yet)" : '0 ✓') : (string) $mismatches,
                )], 3),
            ]),

            Heading::make('By day')->h(3),
            TableBuilder::make([
                Text::make('Day', 'date'),
                Text::make('Generations', 'generations'),
                Text::make('Charged', 'charged', $usd('charged')),
                Text::make('Our cost', 'cost', $usd('cost')),
                Text::make('Profit', 'profit', $usd('profit')),
                Text::make('Pixelforge says', 'reported_cost', fn (array $r) => $r['reported_cost'] === null
                    ? 'not synced' : Money::format($r['reported_cost'], 6).' / '.$r['reported_generations'].' gen'),
                Text::make('Match', 'matches', fn (array $r) => match ($r['matches']) {
                    true => '✓', false => '✗ check', null => '—',
                }),
            ], $byDay->all())->withNotFound(),

            Heading::make('By user')->h(3),
            TableBuilder::make([
                Text::make('User', 'user'),
                Text::make('Succeeded', 'generations'),
                Text::make('Failed (free)', 'failed'),
                Text::make('Charged', 'charged', $usd('charged')),
                Text::make('Pixelforge cost', 'cost', $usd('cost')),
                Text::make('Profit', 'profit', $usd('profit')),
            ], $byUser->all())->withNotFound(),

            Heading::make('Pixelforge usage last synced: '.($lastSync ?? 'never').' (hourly; run php artisan pixelforge:sync-usage for now)')->h(6),
        ];
    }
}
