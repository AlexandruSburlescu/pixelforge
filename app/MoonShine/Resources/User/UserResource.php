<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User;

use App\Models\User;
use App\Models\UserType;
use App\MoonShine\Resources\User\Pages\UserFormPage;
use App\MoonShine\Resources\User\Pages\UserIndexPage;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Users sign up in the app; here admins look them up and super admins change
 * their type. Balances only change through the credit ledger, never by editing.
 *
 * @extends ModelResource<User, UserIndexPage, UserFormPage, null>
 */
#[Icon('users')]
#[Group('Access', 'users')]
#[Order(1)]
class UserResource extends ModelResource
{
    protected string $model = User::class;

    protected string $column = 'email';

    protected array $with = ['userType'];

    protected bool $cursorPaginate = true;

    public function getTitle(): string
    {
        return 'Users';
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::VIEW, Action::DELETE, Action::MASS_DELETE);
    }

    protected function isCan(Ability $ability): bool
    {
        $user = MoonShineAuth::getGuard()->user();

        if (! $user instanceof User) {
            return false;
        }

        return match ($ability) {
            Ability::VIEW, Ability::VIEW_ANY => $user->isAdmin(),
            Ability::UPDATE => $user->hasTypeAtLeast(UserType::SUPER_ADMIN)
                && $this->getItem()?->getKey() !== $user->getKey(), // no self-demotion lockouts
            default => false,
        };
    }

    protected function pages(): array
    {
        return [
            UserIndexPage::class,
            UserFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'email'];
    }
}
