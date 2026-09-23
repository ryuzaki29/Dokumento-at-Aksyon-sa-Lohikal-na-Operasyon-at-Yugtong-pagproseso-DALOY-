<?php

namespace App\Filament\Resources\Routes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RouteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('documentType.name')
                    ->label('Document Type'),
                IconEntry::make('is_active')->boolean(),
                RepeatableEntry::make('steps')
                    ->label('Route Steps')
                    ->schema([
                        TextEntry::make('sequence'),
                        TextEntry::make('office.name')->label('Office'),
                    ])
                    ->columns(2),
                TextEntry::make('creator.name')->label('Created by'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
