<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo Route, so the reference catalog isn't empty on a fresh seed.
 * Reference/documentation data only — nothing in the app reads this against
 * actual documents; routing stays free-choice regardless.
 */
class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');

        $records = Office::where('code', 'REC')->firstOrFail();
        $budget = Office::where('code', 'BUD')->firstOrFail();
        $legal = Office::where('code', 'LEG')->firstOrFail();

        $route = Route::updateOrCreate(
            ['code' => 'REC-BUD-LEG'],
            ['description' => 'Records -> Budget -> Legal approval path', 'is_active' => true, 'created_by' => $adminId],
        );

        $route->steps()->delete();
        $route->steps()->createMany([
            ['office_id' => $records->id, 'sequence' => 1, 'roles' => ['processing_staff']],
            ['office_id' => $budget->id, 'sequence' => 2, 'roles' => ['processing_staff']],
            ['office_id' => $legal->id, 'sequence' => 3, 'roles' => ['approver']],
        ]);
    }
}
