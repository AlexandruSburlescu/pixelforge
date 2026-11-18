<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WebhookEvent\Pages;

use App\Models\WebhookEvent;
use App\MoonShine\Resources\WebhookEvent\WebhookEventResource;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<WebhookEventResource>
 */
final class WebhookEventIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Event', 'event_id'),
            Text::make('Type', 'type')->badge('gray'),
            Text::make('Pixelforge id', 'provider_generation_id'),
            Text::make('Generation', 'generation_id', fn (WebhookEvent $e) => $e->generation_id ? substr($e->generation_id, -8) : '—'),
            Number::make('Delivery #', 'delivery_attempt'),
            Number::make('Duplicates ignored', 'duplicates'),
            Date::make('Event time', 'event_created_at')->format('Y-m-d H:i:s'),
            Date::make('Received', 'created_at')->format('Y-m-d H:i:s')->sortable(),
            Date::make('Processed', 'processed_at')->format('Y-m-d H:i:s'),
        ];
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make('Had duplicates', fn (Builder $q) => $q->where('duplicates', '>', 0)),
            QueryTag::make('Unprocessed', fn (Builder $q) => $q->whereNull('processed_at')),
        ];
    }
}
