<?php

namespace App\Filament\Resources\Routes\Tables;

use App\Models\Route;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('steps.office.institution'))
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('steps_count')
                    ->label('Steps')
                    ->counts('steps'),
                TextColumn::make('steps')
                    ->label('Path')
                    ->state(fn (Route $record): array => $record->steps
                        ->map(function ($step): string {
                            $label = $step->office->institution
                                ? "{$step->office->name} ({$step->office->institution->name})"
                                : $step->office->name;

                            if (filled($step->roles)) {
                                $label .= ' — '.implode(', ', $step->roles);
                            }

                            return $label;
                        })
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
