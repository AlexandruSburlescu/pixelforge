<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Resources\UserType\UserTypeResource;
use App\Support\Money;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<UserResource>
 */
final class UserIndexPage extends IndexPage
{
    /** @return list<FieldContract> */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name'),
            Email::make('Email', 'email'),
            BelongsTo::make('Type', 'userType', 'name', UserTypeResource::class),
            Text::make('Balance', 'balance_nano', fn ($user) => Money::format((int) $user->balance_nano, 4)),
            Date::make('Joined', 'created_at')->format('Y-m-d')->sortable(),
        ];
    }
}
