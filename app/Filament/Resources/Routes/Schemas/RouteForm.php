<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Models\Institution;
use App\Models\Office;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
                Repeater::make('steps')
                    ->relationship()
                    ->orderColumn('sequence')
                    ->schema([
                        Select::make('institution_id')
                            ->label('Institution')
                            ->options(fn () => Institution::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Set $set, Get $get, ?int $state): void {
                                if (blank($state) && filled($get('office_id'))) {
                                    $set('institution_id', Office::find($get('office_id'))?->institution_id);
                                }
                            })
                            ->afterStateUpdated(fn (Set $set) => $set('office_id', null))
                            ->helperText('Narrows the Office choices below — not saved on the step itself.'),
                        Select::make('office_id')
                            ->label('Office')
                            ->options(fn (Get $get) => Office::query()
                                ->where('is_active', true)
                                ->when(
                                    $get('institution_id'),
                                    fn ($query, $institutionId) => $query->where('institution_id', $institutionId),
                                )
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Select::make('roles')
                            ->label('Expected Role(s)')
                            ->options(fn () => Role::query()->pluck('name', 'name'))
                            ->multiple()
                            ->searchable()
                            ->helperText('Reference only — documents who is expected to handle this step. Not enforced; anyone permitted to act on a document still can, regardless of role.'),
                    ])
                    ->reorderableWithDragAndDrop()
                    ->addActionLabel('Add step')
                    ->minItems(1)
                    ->columns(1)
                    ->helperText('Reference only — the order offices are listed in for this named path. Nothing in the app enforces or reads this against actual documents.'),
            ]);
    }
}
