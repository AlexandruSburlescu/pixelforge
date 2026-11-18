<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UserType\Pages;

use App\Models\UserType;
use App\MoonShine\Resources\UserType\UserTypeResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<UserTypeResource, UserType>
 */
final class UserTypeFormPage extends FormPage
{
    /** @return list<ComponentContract|FieldContract> */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Name', 'name')->required(),
                Text::make('Slug', 'slug')
                    ->required()
                    ->hint('Lowercase letters and underscores. Used in code checks, so system slugs are locked.')
                    ->readonly(fn () => (bool) $this->getResource()->getItem()?->is_system),
                Number::make('Level', 'level')
                    ->min(0)->max(1000)
                    ->required()
                    ->hint('User 10 · Admin 50 · Super admin 100. A type passes a check when its level is at least the required type\'s level.'),
                Switcher::make('Can open the admin portal', 'is_admin'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        $type = $item->getOriginal();

        return [
            'name' => ['required', 'string', 'max:64'],
            'slug' => [
                Rule::requiredIf(! $type?->is_system),
                'string', 'max:32', 'regex:/^[a-z][a-z_]*$/',
                Rule::unique('user_types', 'slug')->ignore($type?->getKey()),
            ],
            'level' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_admin' => ['boolean'],
        ];
    }
}
