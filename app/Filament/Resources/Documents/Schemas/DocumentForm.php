<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentForm
{
    /**
     * A document is editable while it's a Draft (not yet submitted),
     * Registered (not yet routed), or Returned (sent back to the originator
     * specifically to be revised) — every other status means it's actively
     * in someone else's hands.
     */
    private static function isEditable(?Document $record): bool
    {
        return $record === null || in_array($record->status, [
            DocumentStatus::Draft, DocumentStatus::Registered, DocumentStatus::Returned,
        ], true);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->relationship('documentType', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(static fn (?Document $record): bool => ! self::isEditable($record)),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->disabled(static fn (?Document $record): bool => ! self::isEditable($record)),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled(static fn (?Document $record): bool => ! self::isEditable($record)),
                FileUpload::make('file_path')
                    ->label('Attachment')
                    ->directory('documents')
                    ->columnSpanFull(),
            ]);
    }
}
