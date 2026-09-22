# Team Worklist — Case Study 8: Document Routing and Approval

Derived from [08_document_routing_balanced.md](08_document_routing_balanced.md). Check items off as they're done; add your name next to an item when you pick it up.

Status as of 2026-09-22: the **required MVP is complete** (all 13 demo requirements, all 4 diagrams, PDCA, tests, seeded data — see [PDCA.md](PDCA.md) and [docs/diagrams.md](docs/diagrams.md)). What's left below is documentation debt from the Institution/User-management extension and pre-submission polish.

## Required MVP (per spec — all should already be checked)

- [x] User can log in
- [x] Access differs according to role (`document_originator`, `processing_staff`, `approver`, `super_admin` via Filament Shield)
- [x] Master data can be created and maintained (Document Type, Office, Institution)
- [x] A primary transaction can be created (Document)
- [x] Transaction moves through at least one review/approval step
- [x] Alternate path exists (Return → Resubmit)
- [x] Meaningful business rule enforced (one current active holder, row-locked)
- [x] Current status clearly visible
- [x] Records searchable and filterable
- [x] Dashboard summarizes key operational data (`DocumentStatsOverview`)
- [x] Simple history/audit trail visible (routing-actions relation manager)
- [x] Report/printable summary available (`RoutingLogReport`, CSV export)
- [x] Seeded demo data included
- [x] 4 required diagrams (System Context, ERD, Process Flow, DFD Level 0)
- [x] PDCA reflection written

## Outstanding — Documentation Debt

These fell behind after the Institution → Office hierarchy and Users module were added on top of the required MVP.

- [ ] Update `docs/diagrams.md` ERD to add the `INSTITUTIONS` table and its FKs (`offices.institution_id`, `users.institution_id`). Document Type intentionally has **no** institution/office FK — it's shared/global across the whole app — _Owner: ____
- [ ] Add a short addendum to `PDCA.md` (or a new "Extensions" section) covering the Institution → Office hierarchy, the Users management module, the multi-role "switch role" feature (topbar menu → `/admin/switch-role`, narrows a multi-role account to one role's permissions at a time), and the new Draft status (see below), since the current PDCA only describes the original required-scope build — _Owner: ____
- [ ] `DocumentStatus` now has 6 cases (Draft + the spec's 5), one past the spec's stated "4–5 transaction statuses" target. Draft is pre-workflow (private to its creator, no `current_office_id`, not counted in `DocumentStatsOverview`) rather than a routing state, so it doesn't change the required Registered→...→Completed flow — worth a one-line justification in the PDCA addendum in case a judge counts literally — _Owner: ____
- [ ] Replace `README.md` (currently the stock Laravel template) with project-specific setup steps: `install.sh` usage, demo login credentials per role, and a link to `PDCA.md` / `docs/diagrams.md` — _Owner: ____

## Outstanding — Demo Data

- [ ] Seed at least one `Institution` and link it to the existing seeded offices/users (`database/seeders/OfficeSeeder.php`, `DemoUserSeeder.php`, or a new `InstitutionSeeder.php` called from `DatabaseSeeder.php`), so the new hierarchy is visible in the live demo instead of only reachable by manually creating records — _Owner: ____

## Pre-Submission Checklist

- [ ] Full test suite green: `docker compose exec php php artisan test`
- [ ] Pint clean on touched files: `docker compose exec php vendor/bin/pint --test`
- [ ] Re-read `08_document_routing_balanced.md` "Scope Control" section — confirm no optional/bonus items (SSO, email, QR, API, etc.) were accidentally started before core MVP polish is done
- [ ] Dry-run the required demo scenario end-to-end: register → receive → forward → submit for approval → return → resubmit → complete, and show the "two simultaneous holders" rule being rejected
- [ ] Confirm PDCA "30–60 second" talking point is rehearsed for the final demo

## Optional / Bonus (do not start before the above is done)

Per spec's Scope Control — only attempt if core + polish above is finished: SSO, email notifications, QR codes, REST API, AI features, advanced analytics, full PDF workflows, digital signatures, external system integration, complex multi-level approval, mobile app.
