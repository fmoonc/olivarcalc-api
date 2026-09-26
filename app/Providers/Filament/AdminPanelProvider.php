<?php

namespace App\Providers\Filament;

use App\Filament\Resources\AffiliateProducts\AffiliateProductResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\OilPrices\OilPriceResource;
use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Filament\Resources\SiteRebuildRequests\SiteRebuildRequestResource;
use App\Filament\Resources\ToolPages\ToolPageResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\LaunchReadiness;
use App\Filament\Widgets\SiteStatus;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('OlivarCalc')
            ->colors([
                // Olive green, per the spec §10.5 palette.
                'primary' => '#3f6212',
            ])
            ->navigationGroups([
                // NavigationGroup rather than a plain array: icons and labels
                // live in one place, and the panel stays consistent.
                NavigationGroup::make('Contenido')->icon('heroicon-o-document-text')->items([
                    NavigationItem::make(ArticleResource::class),
                    NavigationItem::make(CategoryResource::class),
                    NavigationItem::make(ToolPageResource::class),
                    NavigationItem::make(AffiliateProductResource::class),
                ]),
                NavigationGroup::make('Datos oficiales')->icon('heroicon-o-scale')->items([
                    NavigationItem::make(ParameterSetResource::class),
                    NavigationItem::make(OilPriceResource::class),
                ]),
                NavigationGroup::make('Operación')->icon('heroicon-o-cog-6-tooth')->items([
                    NavigationItem::make(UserResource::class),
                    NavigationItem::make(SiteRebuildRequestResource::class),
                ]),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                // Spec §9.9: the launch figures first, the account widget last.
                LaunchReadiness::class,
                SiteStatus::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
