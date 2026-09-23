<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Document;
use App\Support\RouteFlowRenderer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference_no')->label('Reference No.'),
                TextEntry::make('documentType.name')->label('Type'),
                TextEntry::make('route.code')->label('Route')->placeholder('—'),
                TextEntry::make('route_flow')
                    ->label('Process Flow')
                    ->state(fn (Document $record) => RouteFlowRenderer::render($record->route_id, $record))
                    ->html()
                    ->columnSpanFull()
                    ->visible(fn (Document $record): bool => filled($record->route_id)),
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('file_path')
                    ->label('Attachment')
                    ->icon('heroicon-o-paper-clip')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? basename($state) : null)
                    ->url(fn (?string $state): ?string => $state
                        // A signed temporary URL, not a plain one: the disk
                        // FileUpload actually saves to (config('filament.
                        // default_filesystem_disk'), 'local' in this app) has
                        // no 'visibility' => 'public' set, so Laravel's
                        // storage.local route 403s on an unsigned URL — see
                        // Illuminate\Filesystem\ServeFile::hasValidSignature().
                        ? Storage::disk(config('filament.default_filesystem_disk', 'public'))->temporaryUrl($state, now()->addMinutes(30))
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('No attachment')
                    ->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('originatingOffice.name')->label('Originating Office'),
                TextEntry::make('currentOffice.name')->label('Current Holder'),
                TextEntry::make('creator.name')->label('Registered by'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
