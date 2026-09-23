# Document Routing and Approval

A Laravel + FilamentPHP admin panel for routing office documents (memos, requests, endorsements) through a register → route → approve/return → complete workflow, with a full timestamped history and one enforced "current holder" per document. Built as Case Study 8 — see [08_document_routing_balanced.md](08_document_routing_balanced.md) for the spec, [PDCA.md](PDCA.md) for the design reflection, and [docs/diagrams.md](docs/diagrams.md) for the four required diagrams.

## Setup

The project ships as a self-contained Docker stack (PHP-FPM, nginx, PostgreSQL, Redis, Vite).

```bash
./install.sh
```

This writes `.docker/`, `docker-compose.yml`, and env keys (skipping anything already in place, unless run with `--force`), then runs `docker compose up -d --build`. Every file it would overwrite is backed up first. See `./install.sh --help` for `--no-up` / `--force`.

A one-shot `setup` container runs before `php`/`nginx` come up and does everything needed to get a working panel: `php artisan migrate` → `php artisan shield:generate --all --panel=admin` → `php artisan db:seed`, in that order (`.docker/php/setup.sh`). Nothing further to run manually.

The app is served at **http://localhost/admin**.

### Resetting the database

Don't run `php artisan migrate:fresh` by hand — it wipes the `permissions` table but doesn't regenerate it, which leaves every role (including `super_admin`) with zero permissions and hides every module in the panel. To reset cleanly, re-run the `setup` service so `shield:generate` runs again before the seeder:

```bash
docker compose up setup
```

If permissions ever do end up empty (e.g. `migrate:fresh` was run directly), recover with:

```bash
docker compose exec php php artisan shield:generate --all --panel=admin --no-interaction
docker compose exec php php artisan db:seed --no-interaction
```

## Demo logins

Seeded by `DemoUserSeeder` — password is `password` for all (override via `DEMO_USER_PASSWORD` in `.env`):

| Role | Email |
| --- | --- |
| Super Admin | `admin@example.com` |
| Document Originator | `originator@example.com` |
| Processing Staff | `staff@example.com` |
| Approver | `approver@example.com` |

Seeded demo documents cover a completed transaction, a returned-and-resubmitted one, and one mid-flow — enough to exercise the dashboard, routing history, and routing-log report without creating anything by hand.

`TeamSeeder` also seeds the project team's own accounts (all `super_admin`, same `password`) and the UP System + its 8 constituent universities as `Institution` records — see `database/seeders/TeamSeeder.php`.

`RouteSeeder` configures one demo `Route` (Master Data → Routes): Endorsement documents must go Budget Office → Legal / Approving Office, instead of any office. The seeded mid-flow Endorsement document exercises this live — Staff can only forward it to Budget, then only submit it for approval at Legal. Budget has no dedicated demo login, so that hop needs a `super_admin` account (any of the ones above). Document types with no configured route (or an inactive one) keep the original free-choice routing.

## Tests

```bash
docker compose exec php php artisan test
docker compose exec php vendor/bin/pint --test
```

## Docs

- [08_document_routing_balanced.md](08_document_routing_balanced.md) — case study spec and scope control
- [PDCA.md](PDCA.md) — Plan/Do/Check/Act reflection, including the post-MVP Institution/Users/switch-role extensions
- [docs/diagrams.md](docs/diagrams.md) — System Context, ERD, Process Flow, and DFD Level 0 diagrams
- [WORKLIST.md](WORKLIST.md) — remaining pre-submission tasks
