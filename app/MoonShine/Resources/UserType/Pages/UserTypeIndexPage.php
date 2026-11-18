<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UserType\Pages;

use App\MoonShine\Resources\UserType\UserTypeResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<UserTypeResource>
 */
final class UserTypeIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name'),
            Text::make('Slug', 'slug'),
            Number::make('Level', 'level')->sortable(),
            Switcher::make('Admin access', 'is_admin')->readonly(),
            Switcher::make('System', 'is_system')->readonly(),
        ];
    }
}
