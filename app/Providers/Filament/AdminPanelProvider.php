<?php

namespace App\Providers\Filament;

use App\Filament\Pages\SwitchRole;
use App\Http\Middleware\ScopeActiveRole;
use App\Support\ActiveRoleManager;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Master Data',
                'User Management',
                'Audit Trail',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('User Management')
                    ->navigationSort(2),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(function (): string {
                        $role = auth()->user() ? ActiveRoleManager::resolve(auth()->user()) : null;

                        return $role ? "Switch Role (Acting as: {$role->name})" : 'Switch Role';
                    })
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (): string => SwitchRole::getUrl())
                    ->visible(fn (): bool => auth()->user() && ActiveRoleManager::assignedRoles(auth()->user())->count() > 1),
            ])
            ->authMiddleware([
                Authenticate::class,
                // isPersistent: true also registers this with Livewire
                // itself (Livewire::addPersistentMiddleware(), see
                // Filament\Panel\Concerns\HasMiddleware), which re-applies
                // it on every subsequent Livewire AJAX request for a page —
                // not just the initial full page load. Without it, clicking
                // any button/action after the first render would fall back
                // to the union of a multi-role account's ALL assigned
                // roles' permissions instead of the active one.
                ScopeActiveRole::class,
            ], isPersistent: true);
    }
}
