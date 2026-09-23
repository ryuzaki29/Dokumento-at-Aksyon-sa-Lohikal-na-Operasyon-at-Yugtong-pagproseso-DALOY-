<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentRoutingActions;
use App\Models\DocumentType;
use App\Models\Office;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Reference No.')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('documentType.name')
                    ->label('Type')
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currentOffice.name')
                    ->label('Current Holder'),
                TextColumn::make('originatingOffice.name')
                    ->label('Originating Office')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Registered by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(DocumentStatus::class),
                SelectFilter::make('current_office_id')
                    ->label('Current Holder')
                    ->options(fn () => Office::query()->pluck('name', 'id')),
                SelectFilter::make('document_type_id')
                    ->label('Document Type')
                    ->options(fn () => DocumentType::query()->pluck('name', 'id')),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DocumentRoutingActions::submit(),
                DocumentRoutingActions::receive(),
                DocumentRoutingActions::forward(),
                DocumentRoutingActions::submitForApproval(),
                DocumentRoutingActions::approve(),
                DocumentRoutingActions::return(),
                DocumentRoutingActions::resubmit(),
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
