<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
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

/**
 * A configured Route constrains DocumentRoutingService::forward()/
 * submitForApproval() to the exact next office it specifies, and to the
 * matching action (forward for every step but the last, submitForApproval
 * for the last). A DocumentType with no active Route keeps the original
 * free-choice behavior — asserted here too, since that's the fallback every
 * other document type in the app (and every other test) relies on.
 */
class RouteConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Office $originatingOffice;

    private Office $budgetOffice;

    private Office $approvingOffice;

    private Office $decoyOffice;

    private DocumentType $documentType;

    private User $originator;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'ViewAny:Document', 'View:Document', 'Create:Document',
            'Receive:Document', 'Forward:Document', 'SubmitForApproval:Document',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('document_originator', 'web')->syncPermissions([
            'ViewAny:Document', 'View:Document', 'Create:Document',
        ]);
        Role::findOrCreate('processing_staff', 'web')->syncPermissions([
            'ViewAny:Document', 'View:Document', 'Receive:Document',
            'Forward:Document', 'SubmitForApproval:Document',
        ]);

        $this->originatingOffice = Office::factory()->create();
        $this->budgetOffice = Office::factory()->create();
        $this->approvingOffice = Office::factory()->create();
        $this->decoyOffice = Office::factory()->create();
        $this->documentType = DocumentType::factory()->create();

        $this->originator = User::factory()->create();
        $this->originator->assignRole('document_originator');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('processing_staff');

        $admin = User::factory()->create();
        $this->actingAs($admin);

        $route = Route::create(['document_type_id' => $this->documentType->id, 'is_active' => true]);
        $route->steps()->createMany([
            ['office_id' => $this->budgetOffice->id, 'sequence' => 1],
            ['office_id' => $this->approvingOffice->id, 'sequence' => 2],
        ]);
    }

    private function registerDocument(DocumentType $type): Document
    {
        $this->actingAs($this->originator);

        return Document::create([
            'document_type_id' => $type->id,
            'subject' => 'Routed document',
            'originating_office_id' => $this->originatingOffice->id,
            'current_office_id' => $this->originatingOffice->id,
            'status' => DocumentStatus::Registered,
        ]);
    }

    public function test_forward_rejects_an_office_outside_the_configured_route(): void
    {
        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        $this->expectException(ValidationException::class);

        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->decoyOffice->id);
    }

    public function test_forward_follows_the_configured_route_step_by_step(): void
    {
        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->budgetOffice->id);
        $document->refresh();

        $this->assertSame($this->budgetOffice->id, $document->current_office_id);
        $this->assertSame(DocumentStatus::InRouting, $document->status);
    }

    public function test_the_final_route_step_cannot_be_reached_by_forward(): void
    {
        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());
        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->budgetOffice->id);
        $this->staff->update(['office_id' => $this->budgetOffice->id]);

        $this->expectException(ValidationException::class);

        // The route's last step (approvingOffice) must be reached via
        // submitForApproval, not forward.
        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->approvingOffice->id);
    }

    public function test_submit_for_approval_succeeds_only_at_the_routes_final_office(): void
    {
        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());
        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->budgetOffice->id);
        $this->staff->update(['office_id' => $this->budgetOffice->id]);

        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->approvingOffice->id);
        $document->refresh();

        $this->assertSame(DocumentStatus::ForApproval, $document->status);
        $this->assertSame($this->approvingOffice->id, $document->current_office_id);
    }

    public function test_submit_for_approval_rejects_a_non_final_office(): void
    {
        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        $this->expectException(ValidationException::class);

        // budgetOffice is step 1, not the final step — must be forwarded to,
        // not submitted for approval.
        DocumentRoutingService::submitForApproval($document, $this->staff->fresh(), $this->budgetOffice->id);
    }

    public function test_document_types_without_a_route_stay_free_choice(): void
    {
        $unroutedType = DocumentType::factory()->create();
        $document = $this->registerDocument($unroutedType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        // decoyOffice is never part of any route, and this document's type
        // has no configured route at all — free choice, matching every
        // document type before this feature existed.
        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->decoyOffice->id);
        $document->refresh();

        $this->assertSame($this->decoyOffice->id, $document->current_office_id);
    }

    public function test_an_inactive_route_does_not_constrain_routing(): void
    {
        Route::where('document_type_id', $this->documentType->id)->update(['is_active' => false]);

        $document = $this->registerDocument($this->documentType);
        $this->staff->update(['office_id' => $this->originatingOffice->id]);
        DocumentRoutingService::receive($document, $this->staff->fresh());

        DocumentRoutingService::forward($document, $this->staff->fresh(), $this->decoyOffice->id);
        $document->refresh();

        $this->assertSame($this->decoyOffice->id, $document->current_office_id);
    }
}
