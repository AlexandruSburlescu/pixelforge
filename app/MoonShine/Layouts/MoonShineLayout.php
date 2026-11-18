<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\ProfitReportPage;
use App\MoonShine\Resources\ApiCall\ApiCallResource;
use App\MoonShine\Resources\ApiKey\ApiKeyResource;
use App\MoonShine\Resources\CreditLedger\CreditLedgerResource;
use App\MoonShine\Resources\Generation\GenerationResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Resources\UserType\UserTypeResource;
use App\MoonShine\Resources\WebhookEvent\WebhookEventResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * Neutral black/white like the app and Pixelforge itself.
     *
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = NeutralPalette::class;

    protected function menu(): array
    {
        // Items a user can't open (e.g. API keys for plain admins) are hidden by the resources' isCan().
        return [
            MenuGroup::make('Money', [
                MenuItem::make(ProfitReportPage::class),
                MenuItem::make(GenerationResource::class),
                MenuItem::make(CreditLedgerResource::class),
            ], 'banknotes'),
            MenuGroup::make('Pixelforge', [
                MenuItem::make(ApiCallResource::class),
                MenuItem::make(WebhookEventResource::class),
                MenuItem::make(ApiKeyResource::class),
            ], 'bolt'),
            MenuGroup::make('Access', [
                MenuItem::make(UserResource::class),
                MenuItem::make(UserTypeResource::class),
            ], 'users'),
            MenuItem::make(fn () => '/generate', 'Back to the app', 'arrow-uturn-left'),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);
    }

    protected function getFooterCopyright(): string
    {
        return 'Pixelforge Studio';
    }

    /** @return array<string, string> */
    protected function getFooterMenu(): array
    {
        return ['https://pixelforge.promptedit.dev/docs' => 'Pixelforge API docs'];
    }
}
