<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Runs after OfficeSeeder/DemoUserSeeder so the offices/users it links
 * already exist. Links every seeded office and every office-bound demo
 * user to one institution, so the Institution -> Office -> User hierarchy
 * is visible in the demo instead of only reachable by creating records by
 * hand. Document types are intentionally left unlinked — they're global.
 */
class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');

        $institution = Institution::updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Campus', 'is_active' => true, 'created_by' => $adminId],
        );

        Office::whereIn('code', array_column(OfficeSeeder::OFFICES, 'code'))
            ->update(['institution_id' => $institution->id]);

        User::whereIn('email', [
            'originator@example.com',
            'staff@example.com',
            'approver@example.com',
        ])->update(['institution_id' => $institution->id]);
    }
}
