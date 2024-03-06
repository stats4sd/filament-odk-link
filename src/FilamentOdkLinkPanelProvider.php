<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FilamentOdkLinkPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('odk-link')
            ->path('odk-link')
            ->login()
            ->default()
            ->discoverResources(in: __DIR__ . '/Filament/Resources', for: 'Stats4sd\\FilamentOdkLink\\Filament\\Resources')
            ->discoverPages(in: __DIR__ . '/Filament/Pages', for: 'Stats4sd\\FilamentOdkLink\\Filament\\Pages')
            ->discoverWidgets(in: __DIR__ . '/Filament/Widgets', for: 'Stats4sd\\FilamentOdkLink\\Filament\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->userMenuItems([
                MenuItem::make('Home')
                    ->label('Back to main site')
                    ->url(url('/'))
                    ->icon('heroicon-o-home'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
