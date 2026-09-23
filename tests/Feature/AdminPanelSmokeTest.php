<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\DocumentTypes\Pages\CreateDocumentType;
use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Routes\Pages\CreateRoute;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Http\Middleware\ScopeActiveRole;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Institution;
use App\Models\Office;
use App\Models\Route;
use App\Models\User;
use App\Support\ActiveRoleManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Renders the actual Filament pages end-to-end (not just the service layer)
 * to catch template/binding errors that DocumentWorkflowTest's direct
 * service calls wouldn't surface — wrong relationship names, bad component
 * config, missing imports, etc.
 *
 * Runs each test in its own process: Filament/Livewire/Shield keep
 * process-lifetime static caches (permission checks, resource discovery)
 * that don't reliably reset between test methods sharing one process, even
 * with RefreshDatabase and PermissionRegistrar::forgetCachedPermissions() —
 * the same page render that 403s mid-suite passes cleanly when run alone.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie's permission cache can go stale across test methods within
        // the same process (RefreshDatabase resets auto-increment IDs each
        // test, but the registrar's cache doesn't know that), which was
        // producing spurious 403s here.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Production's super_admin holds explicit permission rows rather
        // than a Gate::before bypass (filament-shield.super_admin.
        // define_via_gate is false), so this mirrors that instead of
        // special-casing the test with a gate bypass.
        $methods = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder'];
        $names = [];

        foreach (['Document', 'DocumentType', 'Office', 'User', 'Institution', 'Route'] as $model) {
            foreach ($methods as $method) {
                $names[] = "{$method}:{$model}";
            }
        }

        $names = array_merge($names, [
            'Receive:Document', 'Forward:Document', 'SubmitForApproval:Document',
            'Approve:Document', 'Return:Document', 'Resubmit:Document',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ]);

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role = Role::findOrCreate('super_admin', 'web');
        $role->syncPermissions(Permission::whereIn('name', $names)->get());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    #[RunInSeparateProcess]
    public function test_guest_is_redirected_from_the_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Registered')
            ->assertSee('Completed');
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_documents_list(): void
    {
        $office = Office::factory()->create();
        DocumentType::factory()->create();
        Document::factory()->create([
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/documents')
            ->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_document_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/documents/create')
            ->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_a_document_with_its_routing_history(): void
    {
        $office = Office::factory()->create();
        DocumentType::factory()->create();
        $document = Document::factory()->create([
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/documents/{$document->id}")
            ->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_offices_and_document_types(): void
    {
        $this->actingAs($this->admin)->get('/admin/offices')->assertOk();
        $this->actingAs($this->admin)->get('/admin/document-types')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_routing_log_report(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/routing-log-report')
            ->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_users_list_and_create_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
        $this->actingAs($this->admin)->get('/admin/users/create')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_register_a_new_user_with_a_role(): void
    {
        $institution = Institution::factory()->create();
        $office = Office::factory()->create(['institution_id' => $institution->id]);
        $role = Role::findOrCreate('approver', 'web');

        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Approver',
                'email' => 'jane.approver@example.com',
                'institution_id' => $institution->id,
                'office_id' => $office->id,
                'roles' => [$role->id],
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jane.approver@example.com')->firstOrFail();

        $this->assertSame('Jane Approver', $user->name);
        $this->assertSame($institution->id, $user->institution_id);
        $this->assertSame($office->id, $user->office_id);
        $this->assertTrue($user->hasRole('approver'));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_institutions_list_and_create_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/institutions')->assertOk();
        $this->actingAs($this->admin)->get('/admin/institutions/create')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_create_an_office_under_an_institution(): void
    {
        $institution = Institution::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(CreateOffice::class)
            ->fillForm([
                'institution_id' => $institution->id,
                'code' => 'REG-01',
                'name' => 'Registrar Office',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $office = Office::where('code', 'REG-01')->firstOrFail();

        $this->assertSame($institution->id, $office->institution_id);
    }

    #[RunInSeparateProcess]
    public function test_admin_can_create_a_document_type(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDocumentType::class)
            ->fillForm([
                'code' => 'MEMO-01',
                'name' => 'Internal Memo',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('document_types', [
            'code' => 'MEMO-01',
            'name' => 'Internal Memo',
        ]);
    }

    #[RunInSeparateProcess]
    public function test_document_originating_office_is_forced_to_the_users_own_office(): void
    {
        $documentType = DocumentType::factory()->create();
        $office = Office::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $this->actingAs($this->admin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_type_id' => $documentType->id,
                'subject' => 'Test subject',
                'description' => 'Test description',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::where('subject', 'Test subject')->firstOrFail();

        $this->assertSame($office->id, $document->originating_office_id);
        $this->assertSame($office->id, $document->current_office_id);
        $this->assertSame('Test description', $document->description);
    }

    #[RunInSeparateProcess]
    public function test_admin_can_register_a_document_with_a_reference_route(): void
    {
        $documentType = DocumentType::factory()->create();
        $office = Office::factory()->create();
        $this->admin->update(['office_id' => $office->id]);
        $this->actingAs($this->admin);

        $route = Route::create(['code' => 'REC-LEG', 'is_active' => true]);
        $route->steps()->create(['office_id' => $office->id, 'sequence' => 1]);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_type_id' => $documentType->id,
                'route_id' => $route->id,
                'subject' => 'Routed subject',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::where('subject', 'Routed subject')->firstOrFail();

        $this->assertSame($route->id, $document->route_id);
    }

    #[RunInSeparateProcess]
    public function test_document_view_shows_the_process_flow_with_the_current_step_highlighted(): void
    {
        $officeA = Office::factory()->create(['name' => 'Records Section']);
        $officeB = Office::factory()->create(['name' => 'Legal Office']);
        $documentType = DocumentType::factory()->create();

        $this->actingAs($this->admin);

        $route = Route::create(['code' => 'REC-LEG', 'is_active' => true]);
        $route->steps()->createMany([
            ['office_id' => $officeA->id, 'sequence' => 1],
            ['office_id' => $officeB->id, 'sequence' => 2],
        ]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'route_id' => $route->id,
            'originating_office_id' => $officeA->id,
            'current_office_id' => $officeB->id,
            'status' => DocumentStatus::InRouting,
        ]);

        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertSeeHtml('Legal Office')
            ->assertSeeHtml('Current');
    }

    #[RunInSeparateProcess]
    public function test_a_routed_document_hides_forward_and_submit_for_approval_walks_the_route(): void
    {
        $originOffice = Office::factory()->create();
        $midOffice = Office::factory()->create();
        $approvingOffice = Office::factory()->create();
        $documentType = DocumentType::factory()->create();

        $this->admin->update(['office_id' => $originOffice->id]);
        $this->actingAs($this->admin);

        $route = Route::create(['code' => 'ORIGIN-MID-APPROVE', 'is_active' => true]);
        $route->steps()->createMany([
            ['office_id' => $originOffice->id, 'sequence' => 1],
            ['office_id' => $midOffice->id, 'sequence' => 2],
            ['office_id' => $approvingOffice->id, 'sequence' => 3],
        ]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'route_id' => $route->id,
            'originating_office_id' => $originOffice->id,
            'current_office_id' => $originOffice->id,
            'status' => DocumentStatus::InRouting,
        ]);

        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionHidden('forward')
            ->assertActionVisible('submitForApproval')
            ->callAction('submitForApproval', data: ['remarks' => 'Following the configured route.'])
            ->assertHasNoActionErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::ForApproval, $document->status);
        $this->assertSame($approvingOffice->id, $document->current_office_id);
        $this->assertSame(2, $document->routeActions()->count());
    }

    /**
     * Regression: FileUpload saves to config('filament.default_filesystem_
     * disk') — 'local' in this app, whose config has no 'visibility' =>
     * 'public'. A plain Storage::url() for that disk 404s/403s (Laravel's
     * storage.local route requires either public visibility or a valid
     * signature — see Illuminate\Filesystem\ServeFile), so the attachment
     * link must use a signed temporaryUrl() instead.
     */
    #[RunInSeparateProcess]
    public function test_document_view_shows_a_working_attachment_link(): void
    {
        $disk = config('filament.default_filesystem_disk', 'public');
        $path = 'documents/smoke-test-attachment.txt';
        Storage::disk($disk)->put($path, 'attachment contents');

        $documentType = DocumentType::factory()->create();
        $office = Office::factory()->create();

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'file_path' => $path,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertSeeHtml('smoke-test-attachment.txt');

        $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));

        try {
            $this->get($url)->assertOk();
        } finally {
            Storage::disk($disk)->delete($path);
        }
    }

    #[RunInSeparateProcess]
    public function test_originator_can_save_a_document_as_a_draft(): void
    {
        $documentType = DocumentType::factory()->create();
        $office = Office::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $this->actingAs($this->admin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_type_id' => $documentType->id,
                'subject' => 'Draft subject',
                'description' => 'Not ready yet.',
            ])
            ->call('createDraft')
            ->assertHasNoFormErrors();

        $document = Document::where('subject', 'Draft subject')->firstOrFail();

        $this->assertSame(DocumentStatus::Draft, $document->status);
        $this->assertSame($office->id, $document->originating_office_id);
        $this->assertNull($document->current_office_id);
    }

    #[RunInSeparateProcess]
    public function test_a_draft_is_only_visible_to_its_creator(): void
    {
        $creatorOffice = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $documentType = DocumentType::factory()->create();

        Role::findOrCreate('draft_test_staff', 'web')->syncPermissions(
            Permission::whereIn('name', ['ViewAny:Document', 'View:Document'])->get(),
        );

        $creator = User::factory()->create(['office_id' => $creatorOffice->id]);
        $otherStaff = User::factory()->create(['office_id' => $otherOffice->id]);
        $otherStaff->assignRole('draft_test_staff');

        $draft = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $creatorOffice->id,
            'current_office_id' => null,
            'status' => DocumentStatus::Draft,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($otherStaff);
        $this->assertFalse(DocumentResource::getEloquentQuery()->whereKey($draft->id)->exists());

        $this->actingAs($this->admin);
        $this->assertTrue(DocumentResource::getEloquentQuery()->whereKey($draft->id)->exists());
    }

    #[RunInSeparateProcess]
    public function test_originator_can_submit_a_draft_to_register_it(): void
    {
        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => null,
            'status' => DocumentStatus::Draft,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionVisible('submit')
            ->callAction('submit')
            ->assertHasNoActionErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Registered, $document->status);
        $this->assertSame($office->id, $document->current_office_id);
    }

    #[RunInSeparateProcess]
    public function test_document_registration_is_blocked_without_an_office(): void
    {
        $documentType = DocumentType::factory()->create();

        // $this->admin has no office_id (User::factory() default) — mirrors
        // an account that was never assigned an office.
        $this->actingAs($this->admin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'document_type_id' => $documentType->id,
                'subject' => 'Should not be created',
            ])
            ->call('create');

        $this->assertDatabaseMissing('documents', ['subject' => 'Should not be created']);
    }

    #[RunInSeparateProcess]
    public function test_originator_can_resubmit_a_returned_document_from_the_view_page(): void
    {
        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
            'status' => DocumentStatus::Returned,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionVisible('resubmit')
            ->callAction('resubmit', data: ['remarks' => 'Revised as requested.'])
            ->assertHasNoActionErrors();

        $this->assertSame(DocumentStatus::InRouting, $document->fresh()->status);
    }

    #[RunInSeparateProcess]
    public function test_originator_can_resubmit_a_returned_document_from_the_edit_page(): void
    {
        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
            'status' => DocumentStatus::Returned,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionVisible('resubmit')
            ->callAction('resubmit', data: ['remarks' => 'Revised as requested.'])
            ->assertHasNoActionErrors();

        $this->assertSame(DocumentStatus::InRouting, $document->fresh()->status);
    }

    #[RunInSeparateProcess]
    public function test_a_returned_document_can_actually_be_revised(): void
    {
        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
            'status' => DocumentStatus::Returned,
            'created_by' => $this->admin->id,
            'subject' => 'Original subject',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->fillForm(['subject' => 'Revised subject', 'description' => 'Added missing certification.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();
        $this->assertSame('Revised subject', $document->subject);
        $this->assertSame('Added missing certification.', $document->description);
    }

    #[RunInSeparateProcess]
    public function test_action_buttons_reflect_the_new_status_immediately_after_receiving(): void
    {
        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $office->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
            'status' => DocumentStatus::Registered,
        ]);

        $this->actingAs($this->admin);

        // Same page, same Livewire component, no reload in between — mirrors
        // a user clicking Receive, providing a remark, and saving.
        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionVisible('receive')
            ->callAction('receive', data: ['remarks' => 'Got it.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('receive')
            ->assertActionVisible('forward')
            ->assertActionVisible('submitForApproval');
    }

    #[RunInSeparateProcess]
    public function test_action_buttons_reflect_the_new_status_through_forward_submit_and_approve(): void
    {
        $originOffice = Office::factory()->create();
        $approvingOffice = Office::factory()->create();
        $documentType = DocumentType::factory()->create();
        $this->admin->update(['office_id' => $originOffice->id]);

        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $originOffice->id,
            'current_office_id' => $originOffice->id,
            'status' => DocumentStatus::InRouting,
        ]);

        $this->actingAs($this->admin);

        // Forward moves the document to a different office, so the admin
        // (now not at the document's new current office, though super_admin
        // bypasses the office check) should see the status-derived actions
        // update: still InRouting-gated actions are gone once it's ForApproval.
        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionVisible('submitForApproval')
            ->callAction('submitForApproval', data: ['to_office_id' => $approvingOffice->id, 'remarks' => 'Please review.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('submitForApproval')
            ->assertActionHidden('forward')
            ->assertActionVisible('approve')
            ->callAction('approve', data: ['remarks' => 'Looks good.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('approve')
            ->assertActionHidden('return');

        $this->assertSame(DocumentStatus::Completed, $document->fresh()->status);
    }

    #[RunInSeparateProcess]
    public function test_a_single_role_originator_never_sees_staff_or_approver_actions(): void
    {
        // Real ShieldRoleSeeder grants, not the broad grant setUp() gives
        // $this->admin — this user holds document_originator ONLY, nothing
        // else, so this isolates the action-visibility logic itself from any
        // multi-role/active-role session behavior.
        $originatorRole = Role::findOrCreate('single_role_originator', 'web');
        $originatorRole->syncPermissions(Permission::whereIn('name', [
            'ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document', 'Resubmit:Document',
        ])->get());

        $office = Office::factory()->create();
        $documentType = DocumentType::factory()->create();

        $originator = User::factory()->create(['office_id' => $office->id]);
        $originator->assignRole('single_role_originator');

        // Worst case: the document is at the originator's own office (so
        // userOfficeMatches() is true), while In Routing — the exact status
        // in the bug report's screenshot.
        $document = Document::factory()->create([
            'document_type_id' => $documentType->id,
            'originating_office_id' => $office->id,
            'current_office_id' => $office->id,
            'status' => DocumentStatus::InRouting,
            'created_by' => $originator->id,
        ]);

        $this->actingAs($originator);

        Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionHidden('forward')
            ->assertActionHidden('submitForApproval')
            ->assertActionHidden('return')
            ->assertActionHidden('approve')
            ->assertActionHidden('receive')
            ->assertActionHidden('resubmit')
            ->assertActionHidden('submit')
            ->assertActionHidden('delete');
    }

    #[RunInSeparateProcess]
    public function test_scope_active_role_middleware_is_registered_as_livewire_persistent(): void
    {
        // Filament's authMiddleware(..., isPersistent: true) both applies
        // ScopeActiveRole to the panel's own routes (the initial full page
        // load) AND registers it with Livewire::addPersistentMiddleware()
        // (every subsequent Livewire AJAX request for that same page —
        // clicking a button, submitting a form). Without the persistent
        // flag, only the first page load was ever narrowed to the active
        // role; every interaction after that fell back to the union of a
        // multi-role account's ALL assigned roles' permissions.
        $panel = Filament::getPanel('admin');
        $this->assertContains(ScopeActiveRole::class, $panel->getAuthMiddleware());

        $persistentMiddleware = app(PersistentMiddleware::class)->getPersistentMiddleware();
        $this->assertContains(ScopeActiveRole::class, $persistentMiddleware);
    }

    #[RunInSeparateProcess]
    public function test_a_multi_role_account_acting_as_originator_does_not_see_other_roles_modules(): void
    {
        $permNames = [
            'ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document', 'Resubmit:Document',
            'ViewAny:DocumentType', 'View:DocumentType',
            'ViewAny:Office', 'View:Office',
            'ViewAny:Institution', 'View:Institution',
            'ViewAny:User', 'View:User',
            'ViewAny:Role', 'View:Role',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ];
        foreach ($permNames as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $originator = Role::findOrCreate('nav_test_originator', 'web');
        $originator->syncPermissions(Permission::whereIn('name', [
            'ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document', 'Resubmit:Document',
            'ViewAny:DocumentType', 'View:DocumentType',
            'ViewAny:Office', 'View:Office',
            'ViewAny:Institution', 'View:Institution',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ])->get());

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Holds both roles at once — exactly the real-world scenario: an
        // account that has super_admin AND document_originator, currently
        // acting as the latter via Switch Role.
        $user = User::factory()->create();
        $user->syncRoles([$originator, $superAdmin]);

        $this->actingAs($user);
        ActiveRoleManager::switchTo($user, $originator->id);

        $response = $this->get('/admin');
        $response->assertOk();

        $response->assertSee('Documents')
            ->assertSee('Offices')
            ->assertSee('Document Types')
            ->assertSee('Institutions')
            ->assertSee('Routing Log Report')
            ->assertDontSee('Users', escape: false)
            ->assertDontSee('Roles', escape: false);
    }

    #[RunInSeparateProcess]
    public function test_switch_role_page_is_only_reachable_with_more_than_one_role(): void
    {
        // $this->admin holds only super_admin.
        $this->actingAs($this->admin)->get('/admin/switch-role')->assertForbidden();

        $roleA = Role::findOrCreate('multi_role_a', 'web');
        $roleB = Role::findOrCreate('multi_role_b', 'web');
        $multiRoleUser = User::factory()->create();
        $multiRoleUser->syncRoles([$roleA, $roleB]);

        $this->actingAs($multiRoleUser)->get('/admin/switch-role')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_switching_active_role_narrows_effective_permissions_to_only_that_role(): void
    {
        $roleA = Role::findOrCreate('scoped_role_a', 'web');
        $roleB = Role::findOrCreate('scoped_role_b', 'web');
        $permA = Permission::findOrCreate('Create:Document', 'web');
        $permB = Permission::findOrCreate('Approve:Document', 'web');
        $roleA->syncPermissions([$permA]);
        $roleB->syncPermissions([$permB]);

        $user = User::factory()->create();
        $user->syncRoles([$roleA, $roleB]);

        // Unscoped: permissions from both held roles apply.
        $this->assertTrue($user->can('Create:Document'));
        $this->assertTrue($user->can('Approve:Document'));

        ActiveRoleManager::switchTo($user, $roleB->id);

        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);

        (new ScopeActiveRole)->handle($request, fn () => new Response);

        $this->assertFalse($user->can('Create:Document'));
        $this->assertTrue($user->can('Approve:Document'));
    }

    #[RunInSeparateProcess]
    public function test_admin_can_view_the_routes_list_and_create_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/routes')->assertOk();
        $this->actingAs($this->admin)->get('/admin/routes/create')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_admin_can_configure_a_route_with_ordered_steps(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Role::findOrCreate('processing_staff', 'web');
        Role::findOrCreate('approver', 'web');

        $this->actingAs($this->admin);

        Livewire::test(CreateRoute::class)
            ->fillForm([
                'code' => 'REC-LEG',
                'description' => 'Records straight to Legal',
                'is_active' => true,
                'steps' => [
                    ['office_id' => $officeA->id, 'roles' => ['processing_staff']],
                    ['office_id' => $officeB->id, 'roles' => ['approver']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $route = Route::where('code', 'REC-LEG')->firstOrFail();

        $this->assertSame(
            [$officeA->id, $officeB->id],
            $route->steps()->orderBy('sequence')->pluck('office_id')->all(),
        );
        $this->assertSame(
            [['processing_staff'], ['approver']],
            $route->steps()->orderBy('sequence')->pluck('roles')->all(),
        );
    }
}
