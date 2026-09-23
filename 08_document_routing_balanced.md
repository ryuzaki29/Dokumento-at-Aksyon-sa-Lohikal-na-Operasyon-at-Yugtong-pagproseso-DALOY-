# Case Study 8 — Document Routing and Approval

## Hackathon Scope

This case study is intentionally designed as a **1.5-day MVP challenge**.

To keep all nine team assignments as fair as possible, every case study follows the same technical and functional workload.

### Standard Difficulty Target

**Target difficulty: 3/5**

Each team is expected to implement approximately the same amount of work:

- **3 primary user roles** - (Done)
- **1 master-data module** - (Done - Master Data Module)
- **1 main transaction module** (Document Module)
- **1 approval/review workflow** - (Done)
- **4–5 transaction statuses** (Done)
- **1 non-trivial business rule** (Done)
- **1 dashboard** (Done)
- **1 report/output** (Done)
- **1 basic audit/history view** (Done)
- **4 required diagrams** (Done)
- **1 short PDCA reflection** (Done)
- **Seeded demonstration data** (Done)
- **One complete end-to-end demo scenario** (Done)

Teams should not add large optional modules until the core MVP is working.


## Background

Administrative documents are passed between offices for action and approval. The MVP focuses on current holder, routing history, and a simple approval/return path.

## Core Roles

- Document Originator
- Processing Staff
- Approver

## Standardized Core MVP Challenge

### Master-Data Module

**Document Type Master — memo, request, endorsement, or other document classification.**

### Primary Transaction Module

**Document Routing Record — one document moving through a defined sequence of actions.**

### Required Statuses

- Registered
- In Routing
- For Approval
- Returned
- Completed

### Required End-to-End Workflow

**Originator registers → Processing Staff receives/routes → Approver approves or returns → Completed.**

### Required Business Rule

**A document may have only **one current active holder/office** at any time.**

This business rule is part of the core judging scope and must be demonstrated live.

## Suggested Core Entities

- Document
- DocumentType
- Route
- RouteAction
- Office
- User

Teams may add small lookup/history tables where necessary, but should avoid expanding the MVP into a full enterprise system.


## Suggested Data Definition / Model Guide

### `DocumentType`
| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `code` | `string(50)` | unique |
| `name` | `string(100)` | required |
| `is_active` | `boolean` | default true |
| `created_by` | `foreignId` | FK → users.id; set from authenticated user |
| `updated_by` | `foreignId` | nullable FK → users.id |
| `deleted_at` | `timestamp` | nullable; Laravel SoftDeletes |
| timestamps | `timestamps` | |

### `Document`
| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `reference_no` | `string(50)` | unique |
| `document_type_id` | `foreignId` | FK → document_types.id |
| `subject` | `string(255)` | required |
| `originating_office_id` | `foreignId` | FK → offices.id |
| `current_office_id` | `foreignId` | nullable FK → offices.id |
| `status` | `string(30)` | registered/in_routing/for_approval/returned/completed |
| `file_path` | `string(255)` | nullable |
| `created_by` | `foreignId` | FK → users.id |
| timestamps | `timestamps` | |

### `RouteAction`
| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` | PK |
| `document_id` | `foreignId` | FK → documents.id |
| `from_office_id` | `foreignId` | nullable FK → offices.id |
| `to_office_id` | `foreignId` | nullable FK → offices.id |
| `action` | `string(30)` | received/forwarded/returned/approved/completed |
| `remarks` | `text` | nullable |
| `acted_by` | `foreignId` | FK → users.id |
| `acted_at` | `timestamp` | required |

**Core rule:** `current_office_id` represents the one active holder; route actions must update it consistently.


### General Data Definition Guidance

- These columns are **suggested**, not mandatory production schemas.
- Use Laravel `foreignId()->constrained()` where practical.
- Index commonly searched fields such as status, dates, reference numbers, and foreign keys.
- Use `decimal` rather than floating-point types for money and inventory quantities.
- Statuses may be implemented as validated strings or PHP backed enums.
- Use database constraints plus Laravel validation for important business rules where practical.
- Avoid unnecessary fields and tables during the 1.5-day MVP.
- The submitted ERD should match the final Laravel migrations.


## Minimum Dashboard

- Registered documents
- Pending action
- Returned documents
- Completed documents

Use simple Filament widgets/cards and, optionally, one small chart.

## Minimum Report / Output

- Document routing log showing holder, action, and timestamps.

## Required Demo Scenario

Route a document from originator to processor to approver, return it once, then resubmit and complete. Demonstrate that two simultaneous current holders are prevented.

## Recommended Technology Stack

- **Backend Framework:** Latest stable Laravel release
- **Admin/Application Framework:** Latest stable FilamentPHP release
- **PHP:** Version supported by the selected Laravel release
- **Database:** PostgreSQL or MySQL
- **Frontend:** Filament + Livewire + Tailwind CSS
- **Authentication:** Laravel authentication
- **Authorization:** Laravel Policies/Gates or a compatible roles/permissions package
- **Testing:** Pest or PHPUnit recommended
- **Source Control:** Git

Use whichever database the team is more productive with. The judging focus is on the business workflow, not database brand.


## Standard MVP Requirements

In addition to the case-specific requirements, teams must demonstrate:

- Core records automatically capture `created_by`.
- Normal record deletion uses Laravel SoftDeletes.
- Record visibility is scoped by authenticated user, office, or role where applicable.
- `created_by` is assigned automatically and is not user-selectable.


Every team must demonstrate all of the following:

1. User can log in.
2. Access differs according to role.
3. Master data can be created and maintained.
4. A primary transaction can be created.
5. The transaction moves through at least one review/approval step.
6. The transaction supports at least one alternate path such as return, rejection, cancellation, exception, or correction.
7. A meaningful business rule is enforced by the application.
8. Current status is clearly visible.
9. Records can be searched and filtered.
10. A dashboard summarizes key operational data.
11. A simple history/audit trail is visible.
12. At least one report or printable summary is available.
13. Seeded demo data is included.


## Required Diagrams

All diagrams must describe the **MVP actually implemented**, not an ideal future enterprise system.

### 1. System Context Diagram

Show:

- Application boundary
- The three primary roles
- Any external system actually used or simulated
- Main interactions

### 2. Entity Relationship Diagram (ERD)

Show:

- Core entities/tables
- Primary keys
- Important foreign keys
- Cardinalities
- Status/history table if implemented

The ERD should agree with the submitted Laravel migrations.

### 3. Process Flow Diagram

Show:

- Start and end
- Main user actions
- System actions
- At least one decision point
- Approval/review
- Alternate path such as returned/rejected/cancelled/exception

### 4. Data Flow Diagram — Level 0

Show:

- External actors
- 2–5 major processes
- Main data stores
- Important inputs and outputs

### Optional Bonus

A simple application architecture diagram showing:

```text
User Browser
   |
Laravel + Filament
   |
PostgreSQL / MySQL
```

Add file storage or APIs only if actually used.


## PDCA Requirement

Each team must explain its work using the **Plan–Do–Check–Act** cycle.

### PLAN

Before coding, define:

- Administrative problem
- Primary users
- MVP scope
- Core entities
- Workflow
- One measurable success condition

### DO

Build the smallest working version of the workflow using Laravel and Filament.

The team should prioritize:

- Data model
- Forms and tables
- Role-based access
- Workflow/status transitions
- Core business rule

### CHECK

Test the application using at least **three scenarios**:

1. Normal/successful transaction
2. Returned/rejected/exception transaction
3. Invalid or edge-case transaction that triggers the core business rule

Record at least one issue discovered during testing.

### ACT

Before final presentation, make at least **one improvement** based on the Check phase.

Examples:

- Improve validation
- Simplify a form
- Fix an authorization issue
- Improve status visibility
- Add a useful filter
- Correct a workflow rule

### PDCA Presentation Requirement

During the final demo, teams should spend approximately **30–60 seconds** explaining:

> What did we plan, what did we build, what did we discover during testing, and what did we improve?



## Standard Record Ownership, Tenancy, and Soft Deletes

All primary application models should use a lightweight **user-scoped tenancy / ownership pattern** for the hackathon MVP.

### Required Common Columns

Unless a table is purely system-generated or a simple pivot table, include:

| Column | Suggested Type | Purpose |
|---|---|---|
| `created_by` | `foreignId` | FK → `users.id`; identifies the user who created the record |
| `updated_by` | `foreignId` | nullable FK → `users.id`; identifies the last user who updated the record |
| `created_at` | `timestamp` | Laravel timestamp |
| `updated_at` | `timestamp` | Laravel timestamp |
| `deleted_at` | `timestamp` | nullable; Laravel SoftDeletes |

Recommended migration pattern:

```php
$table->foreignId('created_by')
    ->constrained('users');

$table->foreignId('updated_by')
    ->nullable()
    ->constrained('users')
    ->nullOnDelete();

$table->timestamps();
$table->softDeletes();
```

### Required Model Pattern

Primary Eloquent models should use:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Example extends Model
{
    use SoftDeletes;
}
```

Recommended relationships:

```php
public function creator()
{
    return $this->belongsTo(User::class, 'created_by');
}

public function updater()
{
    return $this->belongsTo(User::class, 'updated_by');
}
```

### User-Scoped Tenancy Rule

For the hackathon MVP, **tenancy means record ownership and visibility based on the authenticated user and their role/office**, rather than a full multi-tenant SaaS architecture.

Examples:

- A normal requester sees records they created or records belonging to their office.
- A processor sees records assigned or routed to their office/role.
- An approver sees records awaiting their action.
- An administrator may see all records.
- `created_by` must be populated from the authenticated user and must **not** be manually selectable in a Filament form.

Example:

```php
'created_by' => auth()->id(),
```

Teams may implement visibility using:

- Laravel Policies,
- Eloquent query scopes,
- Filament `modifyQueryUsing()`,
- or a global scope where appropriate.

### Soft Delete Rule

Normal delete actions should use **soft deletes**.

Teams should demonstrate that:

- deleted records are excluded from normal listings,
- authorized administrators may view trashed records if implemented,
- restoration is optional for the MVP,
- permanent deletion is not required.

Filament resources may optionally include:

```php
Tables\Filters\TrashedFilter::make()
```

and restore actions for administrator roles.

> `created_by` provides ownership/audit scoping for this hackathon. It is intentionally simpler than enterprise multi-tenancy using a dedicated `tenant_id`.


## Suggested Filament Implementation

Recommended use of Filament:

- **1–2 Filament Resources** for core master/transaction management
- Filament Forms for encoding
- Filament Tables for monitoring
- Table filters for status/date/office where appropriate
- Filament Actions for approve, return, issue, transfer, assign, or complete
- Filament Widgets for dashboard metrics
- Laravel Policies/Gates for authorization
- Simple history relation manager or timeline for auditability

## Scope Control

### Required

- Core master module
- Primary transaction module
- Required workflow
- Required business rule
- Dashboard
- Report/output
- Audit/history
- Four diagrams
- PDCA explanation

### Optional / Bonus Only

- SSO
- Email notifications
- QR codes
- REST API
- AI features
- Advanced analytics
- Full PDF workflows
- Digital signatures
- External system integration
- Complex multi-level approval
- Mobile application

These optional features should not be attempted before the core MVP works.

## Suggested Judging Equivalence

This case study is intended to require approximately the same amount of work as the other eight challenges.

Judges should focus on:

- Correct end-to-end workflow
- Correct enforcement of the required business rule
- Authorization
- Usability
- Data-model quality
- Quality of diagrams
- PDCA application
- Completeness of demo

The team should **not receive extra credit simply for implementing a larger scope** if the required MVP is incomplete.