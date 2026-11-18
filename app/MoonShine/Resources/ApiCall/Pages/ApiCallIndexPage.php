<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiCall\Pages;

use App\Models\ApiCall;
use App\MoonShine\Resources\ApiCall\ApiCallResource;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ApiCallResource>
 */
final class ApiCallIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Call', 'endpoint', fn (ApiCall $c) => "{$c->method} {$c->endpoint}"),
            Text::make('Status', 'status_code', fn (ApiCall $c) => (string) ($c->status_code ?? 'no response'))
                ->badge(fn (mixed $s) => match (true) {
                    $s === null => Color::RED,
                    (int) $s < 300 => Color::GREEN,
                    in_array((int) $s, [429, 503], true) => Color::YELLOW,
                    default => Color::RED,
                }),
            Text::make('Error', 'error_code'),
            Number::make('Attempt', 'attempt'),
            Text::make('Retry-After', 'retry_after'),
            Text::make('Remaining/min', 'rate_limit_remaining'),
            Text::make('ms', 'duration_ms'),
            Text::make('Generation', 'generation_id', fn (ApiCall $c) => $c->generation_id ? substr($c->generation_id, -8) : '—'),
            Text::make('Response', 'id', fn (ApiCall $c) => str($c->log?->response_body ?? '')->limit(120)->toString())
                ->columnSelection(hideOnInit: true),
            Date::make('At', 'created_at')->format('Y-m-d H:i:s')->sortable(),
        ];
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make('Errors', fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('status_code')->orWhere('status_code', '>=', 400))),
            QueryTag::make('Rate limited', fn (Builder $q) => $q->where('status_code', 429)),
            QueryTag::make('Overloaded', fn (Builder $q) => $q->where('status_code', 503)),
        ];
    }
}
