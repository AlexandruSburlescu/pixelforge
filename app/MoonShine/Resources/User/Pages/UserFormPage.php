<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\Models\User;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Resources\UserType\UserTypeResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * Only the user type is editable here. Name/email/password belong to the user.
 *
 * @extends FormPage<UserResource, User>
 */
final class UserFormPage extends FormPage
{
    /** @return list<ComponentContract|FieldContract> */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Name', 'name')->readonly(),
                Text::make('Email', 'email')->readonly(),
                BelongsTo::make('Type', 'userType', 'name', UserTypeResource::class)->required(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'user_type_id' => ['required', 'integer', 'exists:user_types,id'],
        ];
    }
}
