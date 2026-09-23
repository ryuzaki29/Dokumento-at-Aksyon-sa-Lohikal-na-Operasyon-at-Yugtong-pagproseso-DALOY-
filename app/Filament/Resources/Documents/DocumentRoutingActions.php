<?php

namespace App\Filament\Resources\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Office;
use App\Models\Route;
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
                && static::routeAllows($record, forApproval: false))
            ->schema([
                Select::make('to_office_id')
                    ->label('Forward to Office')
                    ->options(fn (Document $record) => static::officeOptions($record))
                    ->default(fn (Document $record) => static::routeExpectedOfficeId($record))
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
            ->visible(fn (Document $record): bool => static::userOfficeMatches($record)
                && $record->status === DocumentStatus::InRouting
                && auth()->user()->can('SubmitForApproval:Document')
                && static::routeAllows($record, forApproval: true))
            ->schema([
                Select::make('to_office_id')
                    ->label('Approving Office')
                    ->options(fn (Document $record) => static::officeOptions($record))
                    ->default(fn (Document $record) => static::routeExpectedOfficeId($record))
                    ->required()
                    ->searchable(),
                Textarea::make('remarks')->label('Remarks')->maxLength(1000),
            ])
            ->action(function (Document $record, array $data): void {
                DocumentRoutingService::submitForApproval($record, auth()->user(), (int) $data['to_office_id'], $data['remarks'] ?? null);
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

    /**
     * The active Route configured for this document's type, if any. A
     * document type with no active Route (or an empty one) is unconstrained
     * — every helper below falls back to the original free-choice behavior
     * in that case.
     */
    private static function activeRoute(Document $record): ?Route
    {
        return Route::query()
            ->where('document_type_id', $record->document_type_id)
            ->where('is_active', true)
            ->with('steps.office')
            ->first();
    }

    private static function routeExpectedOfficeId(Document $record): ?int
    {
        $route = static::activeRoute($record);

        if (! $route || $route->steps->isEmpty()) {
            return null;
        }

        return $route->nextOfficeIdAfter($record->current_office_id);
    }

    /**
     * Whether $forApproval (submit-for-approval vs forward) is the
     * transition the configured route expects next, mirroring
     * DocumentRoutingService::assertMatchesConfiguredRoute() so the UI only
     * offers the action that would actually succeed.
     */
    private static function routeAllows(Document $record, bool $forApproval): bool
    {
        $route = static::activeRoute($record);

        if (! $route || $route->steps->isEmpty()) {
            return true;
        }

        $expectedOfficeId = $route->nextOfficeIdAfter($record->current_office_id);

        if ($expectedOfficeId === null) {
            return true;
        }

        return $route->isFinalStepOffice($expectedOfficeId) === $forApproval;
    }

    /**
     * Office choices for the forward/submit-for-approval Select: locked to
     * the single route-expected office when a route is configured, or the
     * original full list of other active offices when it isn't.
     */
    private static function officeOptions(Document $record)
    {
        $expectedOfficeId = static::routeExpectedOfficeId($record);

        if ($expectedOfficeId !== null) {
            return Office::query()->whereKey($expectedOfficeId)->pluck('name', 'id');
        }

        return Office::query()
            ->where('is_active', true)
            ->where('id', '!=', $record->current_office_id)
            ->pluck('name', 'id');
    }
}
