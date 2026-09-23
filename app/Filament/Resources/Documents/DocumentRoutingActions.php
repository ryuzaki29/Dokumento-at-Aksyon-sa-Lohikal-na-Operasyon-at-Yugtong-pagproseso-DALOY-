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
 *
 * Every action calls $record->refresh() right after its service call.
 * DocumentRoutingService re-fetches and locks its own copy of the row
 * (Document::query()->lockForUpdate()->findOrFail(...)) rather than mutating
 * $record directly, so without this, $record — which on a resource page is
 * the exact same instance as $this->record — keeps showing the pre-action
 * status for the rest of that page's lifetime. Each action's own visible()
 * closure reads $record->status, so a stale $record meant the button you
 * just used (and every other status-gated action) kept showing afterward,
 * instead of the page updating to reflect the new status.
 */
class DocumentRoutingActions
{
    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Submit')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('This registers the document and makes it visible for routing. You will no longer be able to freely edit its type, subject, or description.')
            ->visible(fn (Document $record): bool => $record->status === DocumentStatus::Draft
                && $record->created_by === auth()->id()
                && auth()->user()->can('Create:Document'))
            ->action(function (Document $record): void {
                DocumentRoutingService::submit($record, auth()->user());
                $record->refresh();

                Notification::make()->title('Document submitted')->success()->send();
            });
    }

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
                $record->refresh();

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
                && auth()->user()->can('Forward:Document')
                // A document with a configured Route is walked end-to-end by
                // Submit for Approval instead of manual office-to-office
                // forwarding — see submitForApproval() below.
                && ! $record->route_id)
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
                $record->refresh();

                Notification::make()->title('Document forwarded')->success()->send();
            });
    }

    public static function submitForApproval(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for Approval')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->requiresConfirmation(fn (Document $record): bool => (bool) $record->route_id)
            ->modalDescription(fn (Document $record): ?string => $record->route_id
                ? "This will walk the document through the rest of its configured route automatically, ending \"For Approval\" at the route's final office."
                : null)
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::InRouting
                && auth()->user()->can('SubmitForApproval:Document'))
            ->schema(fn (Document $record) => $record->route_id
                ? [Textarea::make('remarks')->label('Remarks')->maxLength(1000)]
                : [
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
                if ($record->route_id) {
                    DocumentRoutingService::submitForApprovalThroughRoute($record, auth()->user(), $data['remarks'] ?? null);
                } else {
                    DocumentRoutingService::submitForApproval($record, auth()->user(), (int) $data['to_office_id'], $data['remarks'] ?? null);
                }

                $record->refresh();

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
                $record->refresh();

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
                $record->refresh();

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
                $record->refresh();

                Notification::make()->title('Document resubmitted')->success()->send();
            });
    }

    private static function userOfficeMatches(Document $record): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') || $record->current_office_id === $user->office_id;
    }
}
