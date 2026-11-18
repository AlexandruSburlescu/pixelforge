<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiCall;

use App\Models\ApiCall;
use App\MoonShine\Resources\ApiCall\Pages\ApiCallIndexPage;
use App\MoonShine\Resources\ReadOnlyResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ReadOnlyResource<ApiCall>
 */
#[Icon('arrows-right-left')]
#[Group('Pixelforge', 'bolt')]
#[Order(1)]
class ApiCallResource extends ReadOnlyResource
{
    protected string $model = ApiCall::class;

    protected array $with = ['log'];

    protected string $sortColumn = 'id';

    public function getTitle(): string
    {
        return 'API calls';
    }

    protected function pages(): array
    {
        return [ApiCallIndexPage::class];
    }

    protected function search(): array
    {
        return ['generation_id', 'idempotency_key', 'error_code'];
    }
}
