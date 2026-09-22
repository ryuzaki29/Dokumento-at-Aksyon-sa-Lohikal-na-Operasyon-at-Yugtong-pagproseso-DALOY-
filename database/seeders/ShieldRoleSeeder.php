<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the roles the panel logs into. Runs after `shield:generate`, so every
 * permission Shield discovered already exists by the time super_admin claims
 * them all.
 */
class ShieldRoleSeeder extends Seeder
{
    /**
     * The three case-study roles.
     */
    public const DOMAIN_ROLES = ['document_originator', 'processing_staff', 'approver'];

    /**
     * Permissions matching the required workflow: Originator registers;
     * Staff receives/forwards/submits/returns; Approver approves/returns.
     * All three can view master data read-only and view the dashboard/report.
     */
    public const ROLE_PERMISSIONS = [
        'document_originator' => [
            'ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document',
            'Resubmit:Document',
            'ViewAny:DocumentType', 'View:DocumentType',
            'ViewAny:Office', 'View:Office',
            'ViewAny:Institution', 'View:Institution',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ],
        'processing_staff' => [
            'ViewAny:Document', 'View:Document',
            'Receive:Document', 'Forward:Document', 'SubmitForApproval:Document', 'Return:Document',
            'ViewAny:DocumentType', 'View:DocumentType',
            'ViewAny:Office', 'View:Office',
            'ViewAny:Institution', 'View:Institution',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ],
        'approver' => [
            'ViewAny:Document', 'View:Document',
            'Approve:Document', 'Return:Document',
            'ViewAny:DocumentType', 'View:DocumentType',
            'ViewAny:Office', 'View:Office',
            'ViewAny:Institution', 'View:Institution',
            'View:DocumentStatsOverview', 'View:RoutingLogReport',
        ],
    ];

    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $superAdmin = Role::findOrCreate(
            config('filament-shield.super_admin.name', 'super_admin'),
            $guard,
        );

        // super_admin holds every permission Shield knows about, so a newly
        // generated permission is picked up on the next seed run too.
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        if (config('filament-shield.panel_user.enabled')) {
            Role::findOrCreate(config('filament-shield.panel_user.name', 'panel_user'), $guard);
        }

        foreach (self::DOMAIN_ROLES as $roleName) {
            $role = Role::findOrCreate($roleName, $guard);

            $permissions = Permission::where('guard_name', $guard)
                ->whereIn('name', self::ROLE_PERMISSIONS[$roleName])
                ->get();

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Artisan::call('cache:clear');
    }
}
