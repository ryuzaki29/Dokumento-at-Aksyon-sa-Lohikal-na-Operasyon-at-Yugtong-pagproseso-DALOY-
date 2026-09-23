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

- [x] Update `docs/diagrams.md` ERD to add the `INSTITUTIONS` table and its FKs (`offices.institution_id`, `users.institution_id`). Document Type intentionally has **no** institution/office FK — it's shared/global across the whole app
- [x] Add a short addendum to `PDCA.md` ("Extensions (post-MVP)" section) covering the Institution → Office hierarchy, the Users management module, the multi-role "switch role" feature, and the new Draft status
- [x] `DocumentStatus`'s 6th case (`Draft`) justified in the PDCA addendum — pre-workflow, doesn't count against the spec's "4–5 transaction statuses" target
- [x] Replace `README.md` (was the stock Laravel template) with project-specific setup steps: `install.sh` usage, demo login credentials per role, and links to `PDCA.md` / `docs/diagrams.md`

## Outstanding — Demo Data

- [x] Seed at least one `Institution` and link it to the existing seeded offices/users (new `database/seeders/InstitutionSeeder.php`, called from `DatabaseSeeder.php` after `OfficeSeeder`) — links all 4 seeded offices and the 3 office-bound demo users to a "Main Campus" institution

## Pre-Submission Checklist

- [x] Full test suite green: `docker compose exec php php artisan test` (34 passed, 163 assertions — 2026-09-23)
- [x] Pint clean on touched files: `docker compose exec php vendor/bin/pint --test` (10 pre-existing style issues found and fixed, 113 files clean — 2026-09-23)
- [ ] Re-read `08_document_routing_balanced.md` "Scope Control" section — confirm no optional/bonus items (SSO, email, QR, API, etc.) were accidentally started before core MVP polish is done
- [ ] Dry-run the required demo scenario end-to-end: register → receive → forward → submit for approval → return → resubmit → complete, and show the "two simultaneous holders" rule being rejected
- [ ] Confirm PDCA "30–60 second" talking point is rehearsed for the final demo

## Optional / Bonus (do not start before the above is done)

Per spec's Scope Control — only attempt if core + polish above is finished: SSO, email notifications, QR codes, REST API, AI features, advanced analytics, full PDF workflows, digital signatures, external system integration, complex multi-level approval, mobile app.
