<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\RouteActionType;
use App\Models\Document;
use App\Models\Route;
use App\Models\RouteAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The five routing transitions, kept independent of Filament so the "one
 * current active holder" business rule can be exercised directly in tests
 * without mounting a Livewire component. Each method locks the Document row
 * inside a transaction and re-validates status/office against that locked,
 * up-to-date data — not whatever the caller happened to pass in — which is
 * what makes two concurrent calls on the same document resolve safely
 * instead of racing.
 */
class DocumentRoutingService
{
    /**
     * Draft -> Registered: the only transition that isn't logged as a
     * RouteAction, matching how the very first Registered state (created
     * directly, not via a draft) was never logged either — routing history
     * only starts once a document is actually received somewhere.
     */
    public static function submit(Document $record, User $actor): Document
    {
        return DB::transaction(function () use ($record, $actor) {
            $document = static::lockOrFail($record, DocumentStatus::Draft, $actor, requireOfficeMatch: false);

            if ($document->created_by !== $actor->id) {
                throw ValidationException::withMessages([
                    'status' => 'Only the document\'s creator can submit it.',
                ]);
            }

            $document->update([
                'status' => DocumentStatus::Registered,
                'current_office_id' => $document->originating_office_id,
            ]);

            return $document;
        });
    }

    public static function receive(Document $record, User $actor, ?string $remarks = null): Document
    {
        return DB::transaction(function () use ($record, $actor, $remarks) {
            $document = static::lockOrFail($record, DocumentStatus::Registered, $actor);

            $document->update(['status' => DocumentStatus::InRouting]);

            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => null,
                'to_office_id' => $document->current_office_id,
                'action' => RouteActionType::Received,
                'remarks' => $remarks,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return $document;
        });
    }

    public static function forward(Document $record, User $actor, int $toOfficeId, ?string $remarks = null): Document
    {
        return static::move($record, $actor, DocumentStatus::InRouting, $toOfficeId, DocumentStatus::InRouting, $remarks);
    }

    public static function submitForApproval(Document $record, User $actor, int $toOfficeId, ?string $remarks = null): Document
    {
        return static::move($record, $actor, DocumentStatus::InRouting, $toOfficeId, DocumentStatus::ForApproval, $remarks);
    }

    /**
     * For a document with a configured Route: auto-advances it through
     * every remaining step in one action, instead of Staff manually
     * forwarding office-to-office. Logs one Forwarded RouteAction per hop
     * (matching the manual history a step-by-step forward would have left),
     * landing at ForApproval, held by the route's last step's office.
     *
     * "Remaining steps" = every step after the one matching the document's
     * current office; if its current office isn't on the route at all, every
     * step is walked, same as DocumentRoutingActions' office-picker options
     * treat "not on the route yet" as "before step one."
     */
    public static function submitForApprovalThroughRoute(Document $record, User $actor, ?string $remarks = null): Document
    {
        return DB::transaction(function () use ($record, $actor, $remarks) {
            $document = static::lockOrFail($record, DocumentStatus::InRouting, $actor);

            $route = Route::query()->with('steps.office')->find($document->route_id);

            if (! $route || $route->steps->isEmpty()) {
                // Keyed to 'remarks' (a real field in this action's modal)
                // rather than 'route_id' (which isn't one) — Filament only
                // renders a validation error against a field that actually
                // exists in the schema; anything else fails silently.
                throw ValidationException::withMessages([
                    'remarks' => 'This document has no configured route to submit through.',
                ]);
            }

            $steps = $route->steps;
            $currentIndex = $steps->search(fn ($step): bool => $step->office_id === $document->current_office_id);
            $remaining = ($currentIndex === false ? $steps : $steps->slice($currentIndex + 1))->values();

            // Already sitting at the route's final office — e.g. a one-step
            // route, or the document was received directly there. Nothing to
            // walk to, so just flip the status in place rather than treating
            // "no hop needed" as an error.
            if ($remaining->isEmpty()) {
                $document->update(['status' => DocumentStatus::ForApproval]);

                RouteAction::create([
                    'document_id' => $document->id,
                    'from_office_id' => $document->current_office_id,
                    'to_office_id' => $document->current_office_id,
                    'action' => RouteActionType::Forwarded,
                    'remarks' => $remarks,
                    'acted_by' => $actor->id,
                    'acted_at' => now(),
                ]);

                return $document;
            }

            $fromOfficeId = $document->current_office_id;
            $lastIndex = $remaining->count() - 1;

            foreach ($remaining as $index => $step) {
                $isLastStep = $index === $lastIndex;

                $document->update([
                    'status' => $isLastStep ? DocumentStatus::ForApproval : DocumentStatus::InRouting,
                    'current_office_id' => $step->office_id,
                ]);

                RouteAction::create([
                    'document_id' => $document->id,
                    'from_office_id' => $fromOfficeId,
                    'to_office_id' => $step->office_id,
                    'action' => RouteActionType::Forwarded,
                    'remarks' => $isLastStep ? $remarks : null,
                    'acted_by' => $actor->id,
                    'acted_at' => now()->addSeconds($index),
                ]);

                $fromOfficeId = $step->office_id;
            }

            return $document;
        });
    }

    public static function approve(Document $record, User $actor, ?string $remarks = null): Document
    {
        return DB::transaction(function () use ($record, $actor, $remarks) {
            $document = static::lockOrFail($record, DocumentStatus::ForApproval, $actor);

            $document->update(['status' => DocumentStatus::Completed]);

            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => $document->current_office_id,
                'to_office_id' => $document->current_office_id,
                'action' => RouteActionType::Approved,
                'remarks' => $remarks,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            // Approval auto-completes for this MVP; a synthetic "completed"
            // entry keeps the log matching the spec's action enum, which
            // lists approved and completed as distinct loggable events.
            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => $document->current_office_id,
                'to_office_id' => $document->current_office_id,
                'action' => RouteActionType::Completed,
                'remarks' => null,
                'acted_by' => $actor->id,
                'acted_at' => now()->addSecond(),
            ]);

            return $document;
        });
    }

    public static function return(Document $record, User $actor, string $remarks): Document
    {
        return DB::transaction(function () use ($record, $actor, $remarks) {
            $document = static::lockOrFail($record, [DocumentStatus::InRouting, DocumentStatus::ForApproval], $actor);

            $fromOfficeId = $document->current_office_id;

            $document->update([
                'status' => DocumentStatus::Returned,
                'current_office_id' => $document->originating_office_id,
            ]);

            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $document->originating_office_id,
                'action' => RouteActionType::Returned,
                'remarks' => $remarks,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return $document;
        });
    }

    public static function resubmit(Document $record, User $actor, ?string $remarks = null): Document
    {
        return DB::transaction(function () use ($record, $actor, $remarks) {
            // Resubmit is done by the Originator, who is not office-bound,
            // so this skips the office-match check applied to staff/approver
            // actions.
            $document = static::lockOrFail($record, DocumentStatus::Returned, $actor, requireOfficeMatch: false);

            $fromOfficeId = $document->current_office_id;

            // Route back to whichever office last forwarded it onward (the
            // processing office), skipping a redundant Receive step; fall
            // back to the originating office if none.
            $lastForward = $document->routeActions()
                ->where('action', RouteActionType::Forwarded)
                ->latest('acted_at')
                ->first();

            $targetOfficeId = $lastForward?->from_office_id ?? $document->originating_office_id;

            $document->update([
                'status' => DocumentStatus::InRouting,
                'current_office_id' => $targetOfficeId,
            ]);

            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $targetOfficeId,
                'action' => RouteActionType::Forwarded,
                'remarks' => trim('Resubmitted. '.($remarks ?? '')),
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return $document;
        });
    }

    private static function move(
        Document $record,
        User $actor,
        DocumentStatus $requiredStatus,
        int $toOfficeId,
        DocumentStatus $resultingStatus,
        ?string $remarks,
    ): Document {
        return DB::transaction(function () use ($record, $actor, $requiredStatus, $toOfficeId, $resultingStatus, $remarks) {
            $document = static::lockOrFail($record, $requiredStatus, $actor);

            $fromOfficeId = $document->current_office_id;

            abort_if($toOfficeId === $fromOfficeId, 422, 'Document is already at that office.');

            $document->update([
                'status' => $resultingStatus,
                'current_office_id' => $toOfficeId,
            ]);

            RouteAction::create([
                'document_id' => $document->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'action' => RouteActionType::Forwarded,
                'remarks' => $remarks,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            return $document;
        });
    }

    /**
     * @param  DocumentStatus|array<DocumentStatus>  $allowedStatuses
     */
    private static function lockOrFail(
        Document $record,
        DocumentStatus|array $allowedStatuses,
        User $actor,
        bool $requireOfficeMatch = true,
    ): Document {
        $document = Document::query()->lockForUpdate()->findOrFail($record->id);

        $allowed = is_array($allowedStatuses) ? $allowedStatuses : [$allowedStatuses];

        if (! in_array($document->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This document was already routed by someone else. Please refresh and try again.',
            ]);
        }

        if ($requireOfficeMatch
            && $document->current_office_id !== $actor->office_id
            && ! $actor->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'status' => 'This document is no longer held by your office.',
            ]);
        }

        return $document;
    }
}
