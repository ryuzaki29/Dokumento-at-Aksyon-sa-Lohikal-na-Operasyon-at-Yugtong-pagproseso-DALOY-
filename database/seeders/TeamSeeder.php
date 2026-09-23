<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Project-team logins (all super_admin, so they can see every module while
 * demoing) plus the full set of UP constituent universities as Institutions,
 * so the Institution hierarchy has real, recognizable data behind it instead
 * of the single generic "Main Campus" from InstitutionSeeder. Idempotent:
 * re-seeding updates the same records instead of duplicating them.
 */
class TeamSeeder extends Seeder
{
    /**
     * Name is a placeholder derived from the email's local part — replace
     * with real names if these should read correctly in a live demo.
     */
    public const ACCOUNTS = [
        'jdsantos18@up.edu.ph',
        'joabina@up.edu.ph',
        'mbapostol1@up.edu.ph',
        'scocampo2@up.edu.ph',
        'bghamor@up.edu.ph',
    ];

    /**
     * UP System plus its eight constituent universities.
     */
    public const INSTITUTIONS = [
        ['code' => 'UP-SYS', 'name' => 'UP System'],
        ['code' => 'UP-BAG', 'name' => 'University of the Philippines Baguio'],
        ['code' => 'UP-CEB', 'name' => 'University of the Philippines Cebu'],
        ['code' => 'UP-DIL', 'name' => 'University of the Philippines Diliman'],
        ['code' => 'UP-LB', 'name' => 'University of the Philippines Los Baños'],
        ['code' => 'UP-MIN', 'name' => 'University of the Philippines Mindanao'],
        ['code' => 'UP-MNL', 'name' => 'University of the Philippines Manila'],
        ['code' => 'UP-OU', 'name' => 'University of the Philippines Open University'],
        ['code' => 'UP-VIS', 'name' => 'University of the Philippines Visayas'],
    ];

    public function run(): void
    {
        $password = env('DEMO_USER_PASSWORD', 'password');
        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

        foreach (self::ACCOUNTS as $email) {
            $name = str(explode('@', $email)[0])->headline();

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$superAdminRole]);
        }

        // created_by is required (not nullable) on institutions, and
        // DatabaseSeeder runs with model events disabled, so HasAuditColumns'
        // creating hook never fires — it must be set explicitly here.
        $adminId = User::where('email', 'admin@example.com')->value('id');

        foreach (self::INSTITUTIONS as $institution) {
            Institution::updateOrCreate(
                ['code' => $institution['code']],
                ['name' => $institution['name'], 'is_active' => true, 'created_by' => $adminId],
            );
        }
    }
}
