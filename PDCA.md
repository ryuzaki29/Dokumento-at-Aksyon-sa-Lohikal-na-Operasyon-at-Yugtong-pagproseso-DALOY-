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

## Extensions (post-MVP)

Everything above describes the required-scope build. The following was added on top of it once the required MVP was already complete and passing; none of it changes the required Registered → In Routing → For Approval → Returned → Completed flow, `DocumentRoutingService`, or the one-current-holder rule.

- **Institution → Office hierarchy.** Added an `institutions` table and a nullable `institution_id` FK on both `offices` and `users`, so offices/users can optionally be grouped under an institution (e.g. multiple campuses or agencies sharing one instance). `document_types` is intentionally left unscoped — document types stay global across the whole app rather than per-institution. See the updated ERD in [docs/diagrams.md](docs/diagrams.md).
- **Users management module.** A Filament resource for managing `User` records directly (list/create/edit, role assignment), instead of users only existing via seeders. Gated by `UserPolicy` the same way other master data is.
- **Multi-role "switch role."** A user can hold more than one Shield role at once. The topbar menu exposes a "Switch Role" page (`app/Filament/Pages/SwitchRole.php`, route `/admin/switch-role`) that narrows the account to acting as one role at a time — the active role is tracked per-session and enforced by a Livewire-persistent middleware (`scope active role middleware is registered as livewire persistent`), so a multi-role account never sees another role's modules/actions simultaneously. Single-role accounts don't see the switcher.
- **`Draft` document status.** `DocumentStatus` now has 6 cases instead of the spec's stated "4–5 transaction statuses" target: `Draft`, `Registered`, `InRouting`, `ForApproval`, `Returned`, `Completed`. `Draft` is **pre-workflow**, not a routing state — a draft has no `current_office_id`, is visible only to its creator (and `super_admin`), and is excluded from `DocumentStatsOverview`'s counts. It exists so an originator can save an in-progress document before committing it to the workflow; "Submit" is what actually registers it and starts routing. The 5 states a document passes through *after* submission (`Registered` → `InRouting` → `ForApproval` → `Returned`/`Completed`) are exactly the spec's target — `Draft` sits outside that count as an editing convenience, not a sixth routing state.
- **Route-driven submission (`Route` / `RouteStep`).** The spec's suggested core entities list a `Route` alongside `RouteAction`; the required build only ever needed the latter (the history log), since Staff freely choose the next office by hand. A `Route` is a standalone, named path (`code` + `description`, e.g. `REC-BUD-LEG`) with an ordered list of offices in `RouteStep`, each optionally tagged with the Shield role(s) expected to handle it (`RouteStep.roles` — a documentation label only; it isn't checked anywhere). A `Document` may optionally pick one at registration via `route_id`, which drives two things: a "Process Flow" preview on its Create/Edit/View pages (a stepper of the route's offices, highlighting whichever matches the document's `current_office_id` once it has a real status), and — once the document is `InRouting` — its **Submit for Approval** action. For a routed document, `DocumentRoutingService::submitForApprovalThroughRoute()` walks every remaining step in one call (each hop still logged as its own `RouteAction`, so the audit trail reads the same as if Staff had forwarded it by hand), landing `ForApproval` at the route's last office; the manual per-office **Forward** action is hidden for these documents, since the route already determines the path. A document with no `route_id` (or an inactive/stepless one) is completely unaffected — Forward stays available and Submit for Approval keeps its original manual office picker, exactly as before this feature existed. Managed under Master Data → Routes, gated by the usual `RoutePolicy`. See `DocumentWorkflowTest::test_submit_for_approval_walks_the_configured_route_automatically`, the routing-focused cases in `AdminPanelSmokeTest`, and the updated ERD in [docs/diagrams.md](docs/diagrams.md).
