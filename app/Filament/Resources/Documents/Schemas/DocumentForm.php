<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->relationship('documentType', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(static fn (?Document $record): bool => $record !== null && $record->status !== DocumentStatus::Registered),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->disabled(static fn (?Document $record): bool => $record !== null && $record->status !== DocumentStatus::Registered),
                Select::make('originating_office_id')
                    ->label('Originating Office')
                    ->relationship('originatingOffice', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->user()->office_id)
                    ->disabled(static fn (?Document $record): bool => $record !== null),
                FileUpload::make('file_path')
                    ->label('Attachment')
                    ->directory('documents')
                    ->columnSpanFull(),
            ]);
    }
}
