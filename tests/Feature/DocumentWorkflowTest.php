<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RouteActionType;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Route;
use App\Models\User;
use App\Services\DocumentRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Office $originatingOffice;

    private Office $processingOffice;

    private Office $approvingOffice;

    private DocumentType $documentType;

    private User $originator;

    private User $staff;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'ViewAny:Document', 'View:Document', 'Create:Document',
            'Receive:Document', 'Forward:Document', 'SubmitForApproval:Document',
            'Approve:Document', 'Return:Document', 'Resubmit:Document',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('document_originator', 'web')->syncPermissions([
            'ViewAny:Document', 'View:Document', 'Create:Document', 'Resubmit:Document',
        ]);
        Role::findOrCreate('processing_staff', 'web')->syncPermissions([
            'ViewAny:Document', 'View:Document', 'Receive:Document', 'Forward:Document',
            'SubmitForApproval:Document', 'Return:Document',
        ]);
        Role::findOrCreate('approver', 'web')->syncPermissions([
            'ViewAny:Document', 'View:Document', 'Approve:Document', 'Return:Document',
        ]);

        $this->originatingOffice = Office::factory()->create();
        $this->processingOffice = Office::factory()->create();
        $this->approvingOffice = Office::factory()->create();
        $this->documentType = DocumentType::factory()->create();

        $this->originator = User::factory()->create();
        $this->originator->assignRole('document_originator');

        $this->staff = User::factory()->create(['office_id' => $this->processingOffice->id]);
        $this->staff->assignRole('processing_staff');

        $this->approver = User::factory()->create(['office_id' => $this->approvingOffice->id]);
        $this->approver->assignRole('approver');
    }

    private function registerDocument(): Document
    {
        // created_by is not mass-assignable by design (HasAuditColumns sets
        // it from the authenticated user), so acting as the originator here
        // mirrors how the Filament Create page really registers a document.
        $this->actingAs($this->originator);

        return Document::create([
            'document_type_id' => $this->documentType->id,
            'subject' => 'Test document',
            'originating_office_id' => $this->originatingOffice->id,
            'current_office_id' => $this->originatingOffice->id,
            'status' => DocumentStatus::Registered,
        ]);
    }

    /** CHECK scenario 1: normal, successful transaction end-to-end. */
    public function test_normal_completion_workflow(): void
    {
        $document = $this->registerDocument();

        // Staff must be at the document's current office to act on it.
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());
        $document->refresh();
        $this->assertSame(DocumentStatus::InRouting, $document->status);

        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->approvingOffice->id);
        $document->refresh();
        $this->assertSame(DocumentStatus::ForApproval, $document->status);
        $this->assertSame($this->approvingOffice->id, $document->current_office_id);

        DocumentRoutingService::approve($document, $this->approver->fresh());
        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);

        $this->assertSame(
            [RouteActionType::Received, RouteActionType::Forwarded, RouteActionType::Approved, RouteActionType::Completed],
            $document->routeActions()->pluck('action')->all(),
        );
    }

    /**
     * A document with a configured Route is walked through every remaining
     * step by a single submitForApprovalThroughRoute() call, instead of
     * Staff manually forwarding office-to-office — each hop still gets its
     * own RouteAction, so the audit trail reads the same either way.
     */
    public function test_submit_for_approval_walks_the_configured_route_automatically(): void
    {
        $document = $this->registerDocument();

        $route = Route::create(['code' => 'TEST-ROUTE', 'is_active' => true]);
        $route->steps()->createMany([
            ['office_id' => $this->originatingOffice->id, 'sequence' => 1],
            ['office_id' => $this->processingOffice->id, 'sequence' => 2],
            ['office_id' => $this->approvingOffice->id, 'sequence' => 3],
        ]);
        $document->update(['route_id' => $route->id]);

        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        DocumentRoutingService::submitForApprovalThroughRoute($document, $this->staff->fresh(), 'Ready for approval.');
        $document->refresh();

        $this->assertSame(DocumentStatus::ForApproval, $document->status);
        $this->assertSame($this->approvingOffice->id, $document->current_office_id);

        $this->assertSame(
            [RouteActionType::Received, RouteActionType::Forwarded, RouteActionType::Forwarded],
            $document->routeActions()->pluck('action')->all(),
        );

        $hops = $document->routeActions()->where('action', RouteActionType::Forwarded)->orderBy('acted_at')->get();
        $this->assertSame($this->originatingOffice->id, $hops[0]->from_office_id);
        $this->assertSame($this->processingOffice->id, $hops[0]->to_office_id);
        $this->assertNull($hops[0]->remarks);
        $this->assertSame($this->processingOffice->id, $hops[1]->from_office_id);
        $this->assertSame($this->approvingOffice->id, $hops[1]->to_office_id);
        $this->assertSame('Ready for approval.', $hops[1]->remarks);
    }

    /**
     * Regression: a document already sitting at its route's only (or last)
     * configured step has zero "remaining" steps to walk. That must still
     * succeed — flipping straight to ForApproval in place — not be treated
     * as an error. It previously threw a ValidationException keyed to a
     * form field ('route_id') that doesn't exist in the action's modal
     * (only 'remarks' does), which Filament silently swallowed: the button
     * visibly did nothing.
     */
    public function test_submit_for_approval_succeeds_when_already_at_the_routes_only_step(): void
    {
        $document = $this->registerDocument();

        $route = Route::create(['code' => 'ONE-STEP', 'is_active' => true]);
        $route->steps()->create(['office_id' => $this->originatingOffice->id, 'sequence' => 1]);
        $document->update(['route_id' => $route->id]);

        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        DocumentRoutingService::submitForApprovalThroughRoute($document, $this->staff->fresh(), 'Ready.');
        $document->refresh();

        $this->assertSame(DocumentStatus::ForApproval, $document->status);
        $this->assertSame($this->originatingOffice->id, $document->current_office_id);

        $this->assertSame(
            [RouteActionType::Received, RouteActionType::Forwarded],
            $document->routeActions()->pluck('action')->all(),
        );
    }

    /** CHECK scenario 2: returned/rejected transaction, then resubmitted to completion. */
    public function test_document_can_be_returned_and_resubmitted(): void
    {
        $document = $this->registerDocument();
        $this->staff->update(['office_id' => $this->originatingOffice->id]);

        DocumentRoutingService::receive($document, $this->staff->fresh());
        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->approvingOffice->id);

        DocumentRoutingService::return($document, $this->approver->fresh(), 'Missing attachment.');
        $document->refresh();
        $this->assertSame(DocumentStatus::Returned, $document->status);
        $this->assertSame($this->originatingOffice->id, $document->current_office_id);

        DocumentRoutingService::resubmit($document, $this->originator->fresh(), 'Attachment added.');
        $document->refresh();
        $this->assertSame(DocumentStatus::InRouting, $document->status);
        // Resubmit routes back to the office that last forwarded it onward.
        $this->assertSame($this->originatingOffice->id, $document->current_office_id);

        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->approvingOffice->id);
        DocumentRoutingService::approve($document, $this->approver->fresh());
        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);

        $this->assertSame(
            [
                RouteActionType::Received,
                RouteActionType::Forwarded,
                RouteActionType::Returned,
                RouteActionType::Forwarded,
                RouteActionType::Forwarded,
                RouteActionType::Approved,
                RouteActionType::Completed,
            ],
            $document->routeActions()->pluck('action')->all(),
        );
    }

    /**
     * CHECK scenario 3: the core business rule — a document may have only
     * one current active holder at any time. An invalid/stale transition
     * must be rejected, leaving status and current_office_id untouched.
     */
    public function test_document_cannot_be_routed_from_an_office_that_does_not_hold_it(): void
    {
        $document = $this->registerDocument();
        // Staff's office (seeded in setUp) does NOT match the document's
        // current_office_id (originatingOffice), simulating a second office
        // trying to act on a document it does not currently hold.
        $this->assertNotSame($this->staff->office_id, $document->current_office_id);

        $this->expectException(ValidationException::class);

        DocumentRoutingService::receive($document, $this->staff);
    }

    public function test_approve_rejects_a_document_that_is_not_for_approval(): void
    {
        $document = $this->registerDocument();
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        $document->refresh();
        $this->assertSame(DocumentStatus::InRouting, $document->status);

        // Document is still In Routing, not For Approval — the approver
        // (at a different office anyway) must not be able to complete it.
        $this->expectException(ValidationException::class);

        DocumentRoutingService::approve($document, $this->approver);

        $document->refresh();
        $this->assertSame(DocumentStatus::InRouting, $document->status);
        $this->assertSame($this->originatingOffice->id, $document->current_office_id);
    }

    public function test_query_scoping_restricts_visibility_by_office(): void
    {
        $document = $this->registerDocument();
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());
        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->approvingOffice->id);

        // Approver's office holds the document — visible to them.
        $this->actingAs($this->approver);
        $this->assertTrue(DocumentResource::getEloquentQuery()->whereKey($document->id)->exists());

        // Staff's office no longer holds it — not visible to them.
        $this->actingAs($this->staff);
        $this->assertFalse(DocumentResource::getEloquentQuery()->whereKey($document->id)->exists());

        // Originator created it — always visible to them regardless of office.
        $this->actingAs($this->originator);
        $this->assertTrue(DocumentResource::getEloquentQuery()->whereKey($document->id)->exists());
    }
}
