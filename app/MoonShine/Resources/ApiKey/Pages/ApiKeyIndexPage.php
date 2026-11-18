<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiKey\Pages;

use App\MoonShine\Resources\ApiKey\ApiKeyResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ApiKeyResource>
 */
final class ApiKeyIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name'),
            Text::make('Environment', 'environment')->badge('gray'),
            Text::make('Key', 'hint'),
            Switcher::make('Active', 'is_active')->readonly(),
            Date::make('Last used', 'last_used_at')->format('Y-m-d H:i'),
        ];
    }
}
