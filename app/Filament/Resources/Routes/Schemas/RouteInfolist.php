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
                TextEntry::make('code'),
                TextEntry::make('description')->placeholder('—'),
                IconEntry::make('is_active')->boolean(),
                RepeatableEntry::make('steps')
                    ->label('Route Steps')
                    ->schema([
                        TextEntry::make('sequence'),
                        TextEntry::make('office.name')->label('Office'),
                        TextEntry::make('office.institution.name')->label('Institution')->placeholder('—'),
                        TextEntry::make('roles')
                            ->label('Expected Role(s)')
                            ->badge()
                            ->placeholder('—'),
                    ])
                    ->columns(4),
                TextEntry::make('creator.name')->label('Created by'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
