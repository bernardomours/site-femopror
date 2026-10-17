<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class UmpPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ump')
            ->path('ump')
            ->login()
            ->colors([
                'primary' => Color::Red,
            ])
            ->brandName('Área da UMP Local')
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Ump/Resources'), for: 'App\Filament\Ump\Resources')
            ->discoverPages(in: app_path('Filament/Ump/Pages'), for: 'App\Filament\Ump\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Ump/Widgets'), for: 'App\Filament\Ump\Widgets')
            ->widgets([
                AccountWidget::class,
                // O `FilamentInfoWidget` saiu: é o cartão de propaganda do
                // Filament, com a versão instalada e links para a documentação
                // e o GitHub. Quem usa este painel é presidente de UMP, não
                // desenvolvedor — e dizer a versão exata do framework para
                // quem está logado é informação que não ajuda ninguém aqui.
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
