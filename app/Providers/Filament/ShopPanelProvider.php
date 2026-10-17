<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Unlock;
use App\Filament\Pages\BlockedDesk;
use App\Http\Middleware\EnsureDeskIsNotBlocked;
use App\Http\Middleware\LockIdleScreen;
use App\Models\ShopSetting;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The counter system: the only UI and the only login of this app. Staff are local users;
 * registration and email password resets are off, and managers add and reset staff.
 */
class ShopPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('shop')
            ->path('')
            ->login()
            ->brandName('Bet Shop')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#f5c542'),
                'gray' => Color::Neutral,
            ])
            ->darkMode(isForced: true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->authenticatedRoutes(function (): void {
                Route::get('unlock', Unlock::class)->name('unlock');
                Route::get('blocked', BlockedDesk::class)->name('blocked');
            })
            ->userMenuItems([
                Action::make('lockScreen')
                    ->label('Lock screen')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->url(fn (): string => route('filament.shop.unlock')),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): ?View => auth()->check() && ! request()->routeIs('filament.shop.unlock')
                    ? view('filament.hooks.idle-lock', [
                        'lockAfterMinutes' => ShopSetting::current()->idle_lock_minutes,
                        'unlockUrl' => route('filament.shop.unlock'),
                    ])
                    : null,
            )
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
                LockIdleScreen::class,
                EnsureDeskIsNotBlocked::class,
            ], isPersistent: true);
    }
}
