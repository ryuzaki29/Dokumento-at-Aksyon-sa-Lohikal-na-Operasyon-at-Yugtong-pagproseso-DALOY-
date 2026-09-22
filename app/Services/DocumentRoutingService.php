<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\RouteActionType;
use App\Models\Document;
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
