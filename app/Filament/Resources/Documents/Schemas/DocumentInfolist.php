<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference_no')->label('Reference No.'),
                TextEntry::make('documentType.name')->label('Type'),
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('originatingOffice.name')->label('Originating Office'),
                TextEntry::make('currentOffice.name')->label('Current Holder'),
                TextEntry::make('creator.name')->label('Registered by'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
