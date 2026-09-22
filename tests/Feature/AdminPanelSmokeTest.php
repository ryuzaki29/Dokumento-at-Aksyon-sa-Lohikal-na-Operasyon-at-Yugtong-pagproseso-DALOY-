<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach (['Document', 'DocumentType', 'Office'] as $model) {
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
}
