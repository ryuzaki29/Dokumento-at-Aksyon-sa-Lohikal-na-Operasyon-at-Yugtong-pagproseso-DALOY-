<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\RouteActionType;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Route;
use App\Models\RouteAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Three demo documents covering the required demo scenario: one fully
 * completed, one returned-then-resubmitted-then-completed, and one sitting
 * mid-flow for live interaction during the presentation.
 *
 * DatabaseSeeder disables model events for this run (WithoutModelEvents), so
 * `reference_no` and `created_by`/`updated_by` are set explicitly here rather
 * than relying on the model's normal creating hooks.
 */
class DocumentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $originator = User::where('email', 'originator@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $approver = User::where('email', 'approver@example.com')->firstOrFail();

        $memo = DocumentType::where('code', 'MEMO')->firstOrFail();
        $request = DocumentType::where('code', 'REQ')->firstOrFail();
        $endorsement = DocumentType::where('code', 'END')->firstOrFail();

        $records = Office::where('code', 'REC')->firstOrFail();
        $legal = Office::where('code', 'LEG')->firstOrFail();
        $route = Route::where('code', 'REC-BUD-LEG')->first();

        $this->completedScenario($memo, $records, $legal, $originator, $staff, $approver);
        $this->returnedAndResubmittedScenario($request, $records, $legal, $originator, $staff, $approver);
        $this->midFlowScenario($endorsement, $records, $originator, $staff, $route);
    }

    private function completedScenario(DocumentType $type, Office $records, Office $legal, User $originator, User $staff, User $approver): void
    {
        $start = now()->subDays(3);

        $document = Document::create([
            'reference_no' => Document::generateReferenceNo(),
            'document_type_id' => $type->id,
            'subject' => 'Request for approval of Q3 procurement plan',
            'originating_office_id' => $records->id,
            'current_office_id' => $legal->id,
            'status' => DocumentStatus::Completed,
            'created_by' => $originator->id,
            'created_at' => $start,
        ]);

        $this->log($document, null, $records->id, RouteActionType::Received, $staff, $start->clone()->addHour());
        $this->log($document, $records->id, $legal->id, RouteActionType::Forwarded, $staff, $start->clone()->addHours(2));
        $this->log($document, $legal->id, $legal->id, RouteActionType::Approved, $approver, $start->clone()->addDay());
        $this->log($document, $legal->id, $legal->id, RouteActionType::Completed, $approver, $start->clone()->addDay()->addSecond());
    }

    private function returnedAndResubmittedScenario(DocumentType $type, Office $records, Office $legal, User $originator, User $staff, User $approver): void
    {
        $start = now()->subDays(2);

        $document = Document::create([
            'reference_no' => Document::generateReferenceNo(),
            'document_type_id' => $type->id,
            'subject' => 'Travel request with incomplete budget certification',
            'originating_office_id' => $records->id,
            'current_office_id' => $legal->id,
            'status' => DocumentStatus::Completed,
            'created_by' => $originator->id,
            'created_at' => $start,
        ]);

        $this->log($document, null, $records->id, RouteActionType::Received, $staff, $start->clone()->addHour());
        $this->log($document, $records->id, $legal->id, RouteActionType::Forwarded, $staff, $start->clone()->addHours(2));
        $this->log(
            $document,
            $legal->id,
            $records->id,
            RouteActionType::Returned,
            $approver,
            $start->clone()->addHours(5),
            'Missing budget office certification.',
        );
        $this->log(
            $document,
            $records->id,
            $records->id,
            RouteActionType::Forwarded,
            $originator,
            $start->clone()->addDay(),
            'Resubmitted. Certification attached.',
        );
        $this->log($document, $records->id, $legal->id, RouteActionType::Forwarded, $staff, $start->clone()->addDay()->addHours(2));
        $this->log($document, $legal->id, $legal->id, RouteActionType::Approved, $approver, $start->clone()->addDays(2));
        $this->log($document, $legal->id, $legal->id, RouteActionType::Completed, $approver, $start->clone()->addDays(2)->addSecond());
    }

    private function midFlowScenario(DocumentType $type, Office $records, User $originator, User $staff, ?Route $route): void
    {
        $start = now()->subHours(4);

        $document = Document::create([
            'reference_no' => Document::generateReferenceNo(),
            'document_type_id' => $type->id,
            'route_id' => $route?->id,
            'subject' => 'Endorsement of new records retention schedule',
            'originating_office_id' => $records->id,
            'current_office_id' => $records->id,
            'status' => DocumentStatus::InRouting,
            'created_by' => $originator->id,
            'created_at' => $start,
        ]);

        $this->log($document, null, $records->id, RouteActionType::Received, $staff, $start->clone()->addMinutes(30));
    }

    private function log(
        Document $document,
        ?int $fromOfficeId,
        ?int $toOfficeId,
        RouteActionType $action,
        User $actor,
        Carbon $actedAt,
        ?string $remarks = null,
    ): void {
        RouteAction::create([
            'document_id' => $document->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'remarks' => $remarks,
            'acted_by' => $actor->id,
            'acted_at' => $actedAt,
        ]);
    }
}
