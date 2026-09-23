<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo Route, so the configurable-routing feature is visible and
 * actually exercised live rather than only reachable by creating records by
 * hand. Configures Endorsement documents to go Budget Office -> Legal /
 * Approving Office — which lines up with DocumentDemoSeeder's mid-flow
 * Endorsement document (sitting at Records, In Routing), so forwarding it
 * during a live demo is constrained to Budget first, then Legal for
 * approval, instead of any office. Budget has no office-bound demo login
 * (only Records and Legal do), so that hop needs a super_admin account —
 * every seeded team login is one (see TeamSeeder).
 */
class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');

        $endorsement = DocumentType::where('code', 'END')->firstOrFail();
        $budget = Office::where('code', 'BUD')->firstOrFail();
        $legal = Office::where('code', 'LEG')->firstOrFail();

        $route = Route::updateOrCreate(
            ['document_type_id' => $endorsement->id],
            ['is_active' => true, 'created_by' => $adminId],
        );

        $route->steps()->delete();
        $route->steps()->createMany([
            ['office_id' => $budget->id, 'sequence' => 1],
            ['office_id' => $legal->id, 'sequence' => 2],
        ]);
    }
}
