<?php

namespace App\Filament\Pages;

use App\Support\ActiveRoleManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SwitchRole extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.switch-role';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ActiveRoleManager::assignedRoles($user)->count() > 1;
    }

    public function getSubheading(): ?string
    {
        $role = ActiveRoleManager::resolve(auth()->user());

        return $role
            ? 'Currently acting as '.Str::headline($role->name).'. Switching restricts you to just that role\'s permissions until you switch again.'
            : null;
    }

    public function table(Table $table): Table
    {
        $activeRoleId = ActiveRoleManager::resolve(auth()->user())?->id;

        return $table
            ->query(fn (): Builder => Role::query()->whereIn(
                'id',
                ActiveRoleManager::assignedRoles(auth()->user())->pluck('id'),
            ))
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::OutlinedShieldCheck),
                TextColumn::make('active')
                    ->label('')
                    ->state(fn (Role $record): ?string => $record->id === $activeRoleId ? 'Currently acting as' : null)
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->recordActions([
                Action::make('switchTo')
                    ->label('Switch to this role')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->action(fn (Role $record) => $this->switchTo($record->id))
                    ->hidden(fn (Role $record): bool => $record->id === $activeRoleId),
            ]);
    }

    public function switchTo(int $roleId): void
    {
        $switched = ActiveRoleManager::switchTo(auth()->user(), $roleId);

        if (! $switched) {
            Notification::make()
                ->title('You do not hold that role.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Role switched.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
