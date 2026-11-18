<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\User;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Money and audit records are never edited from the admin: generations, the
 * ledger, API calls and webhook events are written only by the app itself.
 * Admins can look; nobody can change.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends ModelResource<TModel, \MoonShine\Laravel\Pages\Crud\IndexPage, null, null>
 */
abstract class ReadOnlyResource extends ModelResource
{
    protected bool $cursorPaginate = true;

    protected bool $columnSelection = true;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(
            Action::CREATE, Action::UPDATE, Action::DELETE, Action::MASS_DELETE, Action::VIEW,
        );
    }

    protected function isCan(Ability $ability): bool
    {
        $user = MoonShineAuth::getGuard()->user();

        return $user instanceof User
            && $user->isAdmin()
            && in_array($ability, [Ability::VIEW, Ability::VIEW_ANY], true);
    }
}
