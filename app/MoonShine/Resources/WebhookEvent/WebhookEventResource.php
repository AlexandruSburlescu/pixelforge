<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WebhookEvent;

use App\Models\WebhookEvent;
use App\MoonShine\Resources\ReadOnlyResource;
use App\MoonShine\Resources\WebhookEvent\Pages\WebhookEventIndexPage;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ReadOnlyResource<WebhookEvent>
 */
#[Icon('bell-alert')]
#[Group('Pixelforge', 'bolt')]
#[Order(2)]
class WebhookEventResource extends ReadOnlyResource
{
    protected string $model = WebhookEvent::class;

    protected string $sortColumn = 'id';

    public function getTitle(): string
    {
        return 'Webhook events';
    }

    protected function pages(): array
    {
        return [WebhookEventIndexPage::class];
    }

    protected function search(): array
    {
        return ['event_id', 'provider_generation_id', 'generation_id'];
    }
}
