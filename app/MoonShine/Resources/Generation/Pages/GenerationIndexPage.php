<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Generation\Pages;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\MoonShine\Resources\Generation\GenerationResource;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<GenerationResource>
 */
final class GenerationIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        $money = fn (string $column) => fn (Generation $g) => $g->{$column} === null ? '—' : Money::format((int) $g->{$column}, 6);

        return [
            Text::make('ID', 'id', fn (Generation $g) => substr($g->id, -8))->sortable(),
            Text::make('User', 'user_id', fn (Generation $g) => $g->user?->email ?? '#'.$g->user_id),
            Text::make('Status', 'status', fn (Generation $g) => $g->status->value)
                ->badge(fn (mixed $status) => match ($status instanceof \BackedEnum ? $status->value : (string) $status) {
                    'succeeded' => Color::GREEN,
                    'failed' => Color::RED,
                    'processing' => Color::YELLOW,
                    default => Color::GRAY,
                }),
            Text::make('Prompt', 'prompt', fn (Generation $g) => str($g->prompt)->limit(50)->toString()),
            Text::make('Settings', 'size', fn (Generation $g) => "{$g->size} · {$g->quality}"),
            Text::make('Estimate (max)', 'estimate_max_nano', $money('estimate_max_nano')),
            Text::make('Provider cost', 'provider_cost_nano', $money('provider_cost_nano')),
            Text::make('Charged', 'charge_nano', $money('charge_nano')),
            Text::make('Profit', 'charge_nano', fn (Generation $g) => $g->charge_nano !== null && $g->provider_cost_nano !== null
                ? Money::format($g->charge_nano - $g->provider_cost_nano, 6) : '—'),
            Number::make('Tries', 'submit_attempts'),
            Text::make('Error', 'error_code'),
            Text::make('Pixelforge id', 'provider_id')->columnSelection(hideOnInit: true),
            Date::make('Created', 'created_at')->format('Y-m-d H:i:s')->sortable(),
            Date::make('Completed', 'completed_at')->format('Y-m-d H:i:s')->sortable(),
        ];
    }

    /** @return list<FieldContract> */
    protected function filters(): iterable
    {
        return [
            Select::make('Status', 'status')
                ->options(array_combine(
                    array_map(fn ($s) => $s->value, GenerationStatus::cases()),
                    array_map(fn ($s) => ucfirst($s->value), GenerationStatus::cases()),
                ))
                ->nullable(),
        ];
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make('In flight', fn (Builder $q) => $q->whereIn('status', GenerationStatus::inFlight())),
            QueryTag::make('Retried', fn (Builder $q) => $q->where('submit_attempts', '>', 1)),
            QueryTag::make('Failed', fn (Builder $q) => $q->where('status', GenerationStatus::Failed)),
        ];
    }
}
