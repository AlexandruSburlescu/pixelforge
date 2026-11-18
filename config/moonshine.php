<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserType;
use App\Models\User;
use App\MoonShine\Layouts\MoonShineLayout;
use App\MoonShine\Pages\ProfitReportPage;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Crud\Forms\FiltersForm;
use MoonShine\Crud\Forms\LoginForm;
use MoonShine\Laravel\Exceptions\MoonShineNotFoundException;
use MoonShine\Laravel\Http\Middleware\Authenticate;
use MoonShine\Laravel\Http\Middleware\ChangeLocale;
use MoonShine\Laravel\Pages\ErrorPage;
use MoonShine\Laravel\Pages\LoginPage;
use MoonShine\Laravel\Pages\ProfilePage;

return [
    'title' => env('MOONSHINE_TITLE', 'Pixelforge Admin'),
    'logo' => '/favicon.svg',
    'logo_small' => '/favicon.svg',

    'favicons' => [
        'apple-touch' => '/vendor/moonshine/apple-touch-icon.png',
        '32' => '/vendor/moonshine/favicon-32x32.png',
        '16' => '/vendor/moonshine/favicon-16x16.png',
        'safari-pinned-tab' => '/vendor/moonshine/safari-pinned-tab.svg',
    ],

    // Default flags
    'use_migrations' => false, // we use our own users + user_types tables
    'use_notifications' => true,
    'use_database_notifications' => true,
    'use_routes' => true,
    'use_profile' => false, // profile & password live in the app's own settings (Fortify)

    // Routing
    'domain' => env('MOONSHINE_DOMAIN'),
    'prefix' => env('MOONSHINE_ROUTE_PREFIX', 'admin'),
    'page_prefix' => env('MOONSHINE_PAGE_PREFIX', 'page'),
    'resource_prefix' => env('MOONSHINE_RESOURCE_PREFIX', 'resource'),
    'home_route' => 'moonshine.index',

    // Error handling
    'not_found_exception' => MoonShineNotFoundException::class,

    // Middleware
    'middleware' => [
        ConvertEmptyStringsToNull::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        AuthenticateSession::class,
        ShareErrorsFromSession::class,
        VerifyCsrfToken::class,
        SubstituteBindings::class,
        ChangeLocale::class,
    ],

    // Storage
    'disk' => 'public',
    'disk_options' => [],
    'cache' => 'file',

    // Authentication and profile
    'auth' => [
        'enabled' => true,
        'guard' => 'web', // one session for the app and the admin
        'model' => User::class,
        'middleware' => [
            Authenticate::class,
            EnsureUserType::class.':admin',
        ],
        'pipelines' => [],
    ],

    // Authentication and profile
    'user_fields' => [
        'username' => 'email',
        'password' => 'password',
        'name' => 'name',
        'avatar' => '',
    ],

    // Layout, palette, pages, forms
    'layout' => MoonShineLayout::class,
    'palette' => NeutralPalette::class,

    'forms' => [
        'login' => LoginForm::class,
        'filters' => FiltersForm::class,
    ],

    'pages' => [
        'dashboard' => ProfitReportPage::class, // the report is the admin home
        'profile' => ProfilePage::class,
        'login' => LoginPage::class,
        'error' => ErrorPage::class,
    ],

    // Localizations
    'locale' => 'en',
    'locale_key' => ChangeLocale::KEY,
    'locales' => [
        // en
    ],
];
