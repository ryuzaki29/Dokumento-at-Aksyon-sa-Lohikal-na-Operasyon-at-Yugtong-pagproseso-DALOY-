<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Runs after DemoUserSeeder (so `created_by` has an admin to point at —
 * DatabaseSeeder disables model events during seeding, so HasAuditColumns'
 * creating hook never fires) and assigns each office-bound demo account
 * (staff/approver) to one of the offices it creates here.
 */
class OfficeSeeder extends Seeder
{
    public const OFFICES = [
        ['code' => 'REG', 'name' => "Registrar's Office"],
        ['code' => 'REC', 'name' => 'Records Section'],
        ['code' => 'LEG', 'name' => 'Legal / Approving Office'],
        ['code' => 'BUD', 'name' => 'Budget Office'],
    ];

    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');

        $offices = [];

        foreach (self::OFFICES as $office) {
            $offices[$office['code']] = Office::updateOrCreate(
                ['code' => $office['code']],
                ['name' => $office['name'], 'is_active' => true, 'created_by' => $adminId],
            );
        }

        // Processing Staff works out of Records Section; Approver sits in
        // the Legal / Approving Office — matches DemoUserSeeder's accounts.
        User::where('email', 'staff@example.com')->update(['office_id' => $offices['REC']->id]);
        User::where('email', 'approver@example.com')->update(['office_id' => $offices['LEG']->id]);
    }
}
