<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Route;
use App\Support\RouteFlowRenderer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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

    /**
     * Routes whose steps include the document's originating office (its own
     * `originating_office_id` on Edit, or the registering user's own office
     * on Create — always the same office a document originates from). Falls
     * back to every active Route if none match, so the field never shows an
     * empty list.
     */
    private static function routeOptions(?Document $record): array
    {
        $officeId = $record?->originating_office_id ?? auth()->user()->office_id;

        $query = Route::query()->where('is_active', true);

        $routes = $officeId
            ? (clone $query)->whereHas('steps', fn ($q) => $q->where('office_id', $officeId))->get()
            : collect();

        if ($routes->isEmpty()) {
            $routes = $query->get();
        }

        return $routes
            ->mapWithKeys(fn (Route $route): array => [
                $route->id => $route->description ? "{$route->code} — {$route->description}" : $route->code,
            ])
            ->all();
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
                Select::make('route_id')
                    ->label('Route')
                    ->options(static fn (?Document $record) => self::routeOptions($record))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(static fn (?Document $record): bool => ! self::isEditable($record))
                    ->helperText('Optional — a suggested reference path, for guidance only. It does not restrict actual routing.'),
                Placeholder::make('route_flow_preview')
                    ->label('Process Flow')
                    ->content(static fn (Get $get, ?Document $record) => RouteFlowRenderer::render(
                        $get('route_id') ? (int) $get('route_id') : null,
                        $record,
                    ))
                    ->html()
                    ->visible(static fn (Get $get): bool => filled($get('route_id')))
                    ->columnSpanFull(),
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
