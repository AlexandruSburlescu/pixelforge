<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Generation;

use App\Models\Generation;
use App\MoonShine\Resources\Generation\Pages\GenerationIndexPage;
use App\MoonShine\Resources\ReadOnlyResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ReadOnlyResource<Generation>
 */
#[Icon('photo')]
#[Group('Generations', 'photo')]
#[Order(1)]
class GenerationResource extends ReadOnlyResource
{
    protected string $model = Generation::class;

    protected string $column = 'id';

    protected array $with = ['user'];

    public function getTitle(): string
    {
        return 'Generations';
    }

    protected function pages(): array
    {
        return [GenerationIndexPage::class];
    }

    protected function search(): array
    {
        return ['id', 'provider_id', 'prompt'];
    }
}
