<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only routing/audit history for a Document. Rows are only ever created
 * by DocumentRoutingActions, so no create/edit/delete/associate actions are
 * exposed here — this is a log, not an editable list.
 */
class RouteActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'routeActions';

    protected static ?string $title = 'Routing History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('acted_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('fromOffice.name')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('toOffice.name')
                    ->label('To')
                    ->placeholder('—'),
                TextColumn::make('actor.name')
                    ->label('By'),
                TextColumn::make('remarks')
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->defaultSort('acted_at', 'asc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
