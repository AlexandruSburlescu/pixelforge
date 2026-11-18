<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UserType;

use App\Models\User;
use App\Models\UserType;
use App\MoonShine\Resources\UserType\Pages\UserTypeFormPage;
use App\MoonShine\Resources\UserType\Pages\UserTypeIndexPage;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Admins can see user types; only super admins can add or change them.
 * System types (user, admin, super_admin) and types still in use can't be deleted.
 *
 * @extends ModelResource<UserType, UserTypeIndexPage, UserTypeFormPage, null>
 */
#[Icon('identification')]
#[Group('Access', 'users')]
#[Order(2)]
class UserTypeResource extends ModelResource
{
    protected string $model = UserType::class;

    protected string $column = 'name';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    public function getTitle(): string
    {
        return 'User types';
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW, Action::MASS_DELETE);
    }

    protected function isCan(Ability $ability): bool
    {
        $user = MoonShineAuth::getGuard()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (in_array($ability, [Ability::VIEW, Ability::VIEW_ANY], true)) {
            return $user->isAdmin();
        }

        if (! $user->hasTypeAtLeast(UserType::SUPER_ADMIN)) {
            return false;
        }

        if ($ability === Ability::DELETE) {
            $type = $this->getItem();

            return $type instanceof UserType && ! $type->is_system && ! $type->users()->exists();
        }

        return true;
    }

    protected function pages(): array
    {
        return [
            UserTypeIndexPage::class,
            UserTypeFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['slug', 'name'];
    }
}
