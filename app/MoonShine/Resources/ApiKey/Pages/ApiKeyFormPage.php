<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiKey\Pages;

use App\Models\ApiKey;
use App\MoonShine\Resources\ApiKey\ApiKeyResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<ApiKeyResource, ApiKey>
 */
final class ApiKeyFormPage extends FormPage
{
    /** @return list<ComponentContract|FieldContract> */
    protected function fields(): iterable
    {
        $creating = ! $this->getResource()->getItem()?->exists;

        return [
            Box::make([
                ID::make(),
                Text::make('Name', 'name')->required(),
                Text::make('Environment', 'environment')
                    ->required()
                    ->default((string) config('pixelforge.environment'))
                    ->hint('This app uses the active key whose environment is "'.config('pixelforge.environment').'".'),
                // Write-only: an empty field on edit keeps the stored secret.
                Password::make('API key', 'secret')
                    ->required($creating)
                    ->customAttributes(['autocomplete' => 'off'])
                    ->hint($creating ? 'Shown once in the Pixelforge dashboard.' : 'Leave empty to keep the current key.'),
                Password::make('Webhook signing secret (whsec_…)', 'webhook_secret')
                    ->required($creating)
                    ->customAttributes(['autocomplete' => 'off'])
                    ->hint($creating ? '' : 'Leave empty to keep the current secret.'),
                Switcher::make('Active', 'is_active')->default(true),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        $creating = ! $item->getOriginal()?->exists;

        return [
            'name' => ['required', 'string', 'max:64'],
            'environment' => ['required', 'string', 'max:32', 'alpha_dash'],
            'secret' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'webhook_secret' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
