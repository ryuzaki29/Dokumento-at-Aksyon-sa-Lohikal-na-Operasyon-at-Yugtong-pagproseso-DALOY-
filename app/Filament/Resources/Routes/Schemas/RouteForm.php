<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Models\Office;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->label('Document Type')
                    ->relationship('documentType', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->unique(ignoreRecord: true)
                    ->helperText('One route per document type. A type with no route here (or an inactive one) keeps free-choice routing — staff can forward to any office.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
                Repeater::make('steps')
                    ->relationship()
                    ->orderColumn('sequence')
                    ->schema([
                        Select::make('office_id')
                            ->label('Office')
                            ->options(fn () => Office::query()->where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    ])
                    ->reorderableWithDragAndDrop()
                    ->addActionLabel('Add step')
                    ->minItems(1)
                    ->columns(1)
                    ->helperText('Order matters: the LAST step is where the document is submitted "For Approval." Every step before it is a forward-only stop.'),
            ]);
    }
}
