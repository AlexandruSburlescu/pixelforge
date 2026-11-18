<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiKey;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserType;
use App\MoonShine\Resources\ApiKey\Pages\ApiKeyFormPage;
use App\MoonShine\Resources\ApiKey\Pages\ApiKeyIndexPage;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Super admins only. Secrets are write-only: they're encrypted at rest, never
 * shown again after saving, and the list only shows the last 4 characters.
 *
 * @extends ModelResource<ApiKey, ApiKeyIndexPage, ApiKeyFormPage, null>
 */
#[Icon('key')]
#[Group('Pixelforge', 'bolt')]
#[Order(3)]
class ApiKeyResource extends ModelResource
{
    protected string $model = ApiKey::class;

    protected string $column = 'name';

    public function getTitle(): string
    {
        return 'API keys';
    }

    protected function activeActions(): ListOf
    {
        // Deleting would orphan generations and usage history: deactivate instead.
        return parent::activeActions()->except(Action::VIEW, Action::DELETE, Action::MASS_DELETE);
    }

    protected function isCan(Ability $ability): bool
    {
        $user = MoonShineAuth::getGuard()->user();

        return $user instanceof User && $user->hasTypeAtLeast(UserType::SUPER_ADMIN);
    }

    protected function pages(): array
    {
        return [ApiKeyIndexPage::class, ApiKeyFormPage::class];
    }
}
