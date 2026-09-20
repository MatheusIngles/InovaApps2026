<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Configuracoes;
use App\Filament\Pages\Painel;
use App\Http\Middleware\SetCompanyContext;
use App\Models\Customer;
use App\Support\AvatarIniciais;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login(Login::class)
            ->registration(Register::class) // cadastro cria a empresa (tenant) e o primeiro usuário
            ->passwordReset()
            ->globalSearch(false)
            ->databaseNotifications()
            ->brandName('Seer')
            ->brandLogo(asset('images/seer-logo.png'))
            ->brandLogoHeight('3rem')
            ->topNavigation()
            ->navigation(fn (): bool => auth()->check() && Customer::exists()) // sem dados, só a planilha inicial
            ->defaultAvatarProvider(AvatarIniciais::class)
            ->userMenuItems([Action::make('configuracoes')->label('Configurações')->icon(Heroicon::OutlinedCog6Tooth)->url(fn (): string => Configuracoes::getUrl())])
            ->homeUrl(fn () => url('/'))
            ->font('Plus Jakarta Sans')
            ->darkMode(true)
            ->themeSwitcher(true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors(['primary' => Color::Blue, 'gray' => Color::Slate])
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Painel::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('tema').view('texto-tamanho'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('vlibras').view('titulo-topo').view('dicas').view('audio-contexto').view('animacoes').view('navegacao-voz'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetCompanyContext::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
