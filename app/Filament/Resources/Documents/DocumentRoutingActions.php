<?php

namespace App\Filament\Resources\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Office;
use App\Services\DocumentRoutingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Filament UI wiring (labels, icons, visibility, form fields) for the five
 * routing transitions. All the transactional business logic — and the
 * enforcement of the "one current active holder" rule — lives in
 * DocumentRoutingService, so it can be exercised directly in tests.
 */
class DocumentRoutingActions
{
    public static function receive(): Action
    {
        return Action::make('receive')
            ->label('Receive')
            ->icon('heroicon-o-inbox-arrow-down')
            ->color('info')
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::Registered
                && auth()->user()->can('Receive:Document'))
            ->schema([
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::receive($record, auth()->user(), $data['remarks'] ?? null);

                Notification::make()->title('Document received')->success()->send();
            });
    }

    public static function forward(): Action
    {
        return Action::make('forward')
            ->label('Forward')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::InRouting
                && auth()->user()->can('Forward:Document'))
            ->schema([
                Select::make('to_office_id')
                    ->label('Forward to Office')
                    ->options(fn (Document $record) => Office::query()
                        ->where('is_active', true)
                        ->where('id', '!=', $record->current_office_id)
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::forward($record, auth()->user(), (int) $data['to_office_id'], $data['remarks'] ?? null);

                Notification::make()->title('Document forwarded')->success()->send();
            });
    }

    public static function submitForApproval(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for Approval')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::InRouting
                && auth()->user()->can('SubmitForApproval:Document'))
            ->schema([
                Select::make('to_office_id')
                    ->label('Approving Office')
                    ->options(fn (Document $record) => Office::query()
                        ->where('is_active', true)
                        ->where('id', '!=', $record->current_office_id)
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::submitForApproval($record, auth()->user(), (int) $data['to_office_id'], $data['remarks'] ?? null);

                Notification::make()->title('Document submitted for approval')->success()->send();
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::ForApproval
                && auth()->user()->can('Approve:Document'))
            ->schema([
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::approve($record, auth()->user(), $data['remarks'] ?? null);

                Notification::make()->title('Document approved and completed')->success()->send();
            });
    }

    public static function return(): Action
    {
        return Action::make('return')
            ->label('Return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && in_array($record->status, [DocumentStatus::InRouting, DocumentStatus::ForApproval], true)
                && auth()->user()->can('Return:Document'))
            ->schema([
                Textarea::make('remarks')
                    ->label('Reason for return')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::return($record, auth()->user(), $data['remarks']);

                Notification::make()->title('Document returned to originator')->warning()->send();
            });
    }

    public static function resubmit(): Action
    {
        return Action::make('resubmit')
            ->label('Resubmit')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (Document $record): bool => $record->status === DocumentStatus::Returned
                && $record->created_by === auth()->id()
                && auth()->user()->can('Resubmit:Document'))
            ->schema([
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::resubmit($record, auth()->user(), $data['remarks'] ?? null);

                Notification::make()->title('Document resubmitted')->success()->send();
            });
    }

    private static function userOfficeMatches(Document $record): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') || $record->current_office_id === $user->office_id;
    }
}
