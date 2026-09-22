# PDCA Reflection — Case Study 8: Document Routing and Approval

## Plan

- **Administrative problem:** Memos, requests, and endorsements move between offices with no shared record of who currently holds a document or what happened to it, making status "where is this?" a manual, ask-around process.
- **Primary users:** Document Originator (registers and resubmits), Processing Staff (receives/forwards), Approver (approves/returns).
- **MVP scope:** register a document, route it office-to-office, submit it for approval, approve or return it, and resubmit a returned document — with a full timestamped history.
- **Core entities:** `Document`, `DocumentType`, `Office`, `RouteAction`, `User`.
- **Workflow:** Originator registers → Staff receives/routes → Approver approves or returns → Completed.
- **Success condition:** a document can be routed from originator to processor to approver, returned once, resubmitted, and completed, while the system provably prevents two offices from simultaneously holding the same document as "current."

## Do

Built with Laravel + FilamentPHP:

- `documents`, `document_types`, `offices`, `route_actions` migrations, all with `created_by`/`updated_by` audit columns and soft deletes where applicable.
- `DocumentRoutingService` centralizes the five transitions (receive, forward, submit-for-approval, approve, return, resubmit) so the "one current active holder" rule is enforced in one place — a locked, re-validated read of the document row inside a DB transaction — rather than scattered across Filament actions.
- Filament resources for `Document`, `DocumentType`, `Office` with role-scoped visibility (`DocumentResource::getEloquentQuery()`), a routing-actions relation manager for history, a dashboard stats widget, and a routing-log report page with filters and CSV export.
- Filament Shield for the three roles plus a super-admin.
- Seeders for offices, document types, one login per role, and three demo documents covering completed / returned-and-resubmitted / mid-flow states.

## Check

Tested via `tests/Feature/DocumentWorkflowTest.php` and `tests/Feature/AdminPanelSmokeTest.php` against three required scenarios:

1. **Normal/successful transaction** — `normal completion workflow`: register → receive → forward → submit for approval → approve, ending `Completed`.
2. **Returned/exception transaction** — `document can be returned and resubmitted`: routed, returned to the originating office, resubmitted, completed.
3. **Invalid/edge case triggering the core business rule** — `document cannot be routed from an office that does not hold it` and `approve rejects a document that is not for approval`: actions from the wrong office or wrong status are rejected with a validation error instead of silently succeeding.

**Issue discovered:** `test_admin_can_view_the_dashboard` failed — the dashboard stats widget (Registered/Pending/Returned/Completed counts) lazy-loads by default in this Filament version, so the counts weren't present in the initial page response the test (and a judge's first glance) would see.

## Act

Set `protected static bool $isLazy = false;` on `DocumentStatsOverview` so the dashboard's four counts render on first load instead of after a follow-up AJAX request. The underlying query is already scoped and cheap (a handful of `count()` calls against the same visibility-filtered query the resource uses), so there was no real benefit to deferring it — only a cost to how the dashboard reads during a live demo. All 14 tests pass after the change.
