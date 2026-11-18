<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\ApiCall\ApiCallResource;
use App\MoonShine\Resources\ApiKey\ApiKeyResource;
use App\MoonShine\Resources\CreditLedger\CreditLedgerResource;
use App\MoonShine\Resources\Generation\GenerationResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Resources\UserType\UserTypeResource;
use App\MoonShine\Resources\WebhookEvent\WebhookEventResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                GenerationResource::class,
                CreditLedgerResource::class,
                ApiCallResource::class,
                WebhookEventResource::class,
                ApiKeyResource::class,
                UserResource::class,
                UserTypeResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
            ]);
    }
}
