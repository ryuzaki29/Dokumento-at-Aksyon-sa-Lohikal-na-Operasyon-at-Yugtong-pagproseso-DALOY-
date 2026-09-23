# Case Study 8 — Required Diagrams

These four diagrams describe the MVP as actually implemented (see `database/migrations/`, `app/Models/`, `app/Services/DocumentRoutingService.php`). They supersede `mermaid-diagram.png`, which was drawn against an earlier, larger schema (separate status/action lookup tables, a custom `roles` table, attachments, and history tables) that was never built — the real app uses plain status strings on `documents` and Spatie/Filament Shield for roles.

## 1. System Context Diagram

```mermaid
flowchart TB
    Originator["Document Originator"]
    Staff["Processing Staff"]
    Approver["Approver"]
    Admin["Super Admin"]

    subgraph System["Document Routing & Approval (Laravel + Filament)"]
        App["Admin Panel\n(/admin)"]
    end

    DB[("PostgreSQL Database")]

    Originator -- "registers documents, resubmits returned ones" --> App
    Staff -- "receives / forwards documents" --> App
    Approver -- "approves / returns documents" --> App
    Admin -- "manages master data, all records" --> App

    App -- "reads/writes" --> DB
    App -- "current status & routing history" --> Originator
    App -- "pending-action queue" --> Staff
    App -- "for-approval queue" --> Approver
```

## 2. Entity Relationship Diagram (ERD)

Matches `database/migrations/2026_09_22_1000*` exactly.

```mermaid
erDiagram
    INSTITUTIONS ||--o{ OFFICES : "owns"
    INSTITUTIONS ||--o{ USERS : "employs"
    OFFICES ||--o{ USERS : "staffs"
    OFFICES ||--o{ DOCUMENTS : "originates"
    OFFICES ||--o{ DOCUMENTS : "currently holds"
    OFFICES ||--o{ ROUTE_ACTIONS : "from"
    OFFICES ||--o{ ROUTE_ACTIONS : "to"
    DOCUMENT_TYPES ||--o{ DOCUMENTS : "classifies"
    DOCUMENTS ||--o{ ROUTE_ACTIONS : "has history"
    USERS ||--o{ ROUTE_ACTIONS : "acts on"
    USERS ||--o{ DOCUMENTS : "creates"
    ROUTES ||--o{ ROUTE_STEPS : "orders"
    OFFICES ||--o{ ROUTE_STEPS : "visited at"
    ROUTES ||--o{ DOCUMENTS : "suggested for (optional)"

    INSTITUTIONS {
        bigint id PK
        string code
        string name
        boolean is_active
        bigint created_by FK
        bigint updated_by FK
        timestamp deleted_at
    }

    OFFICES {
        bigint id PK
        bigint institution_id FK
        string code
        string name
        boolean is_active
        bigint created_by FK
        bigint updated_by FK
        timestamp deleted_at
    }

    USERS {
        bigint id PK
        bigint office_id FK
        bigint institution_id FK
        string name
        string email
        string password
    }

    DOCUMENT_TYPES {
        bigint id PK
        string code
        string name
        boolean is_active
        bigint created_by FK
        bigint updated_by FK
        timestamp deleted_at
    }

    DOCUMENTS {
        bigint id PK
        string reference_no
        bigint document_type_id FK
        bigint route_id FK "nullable, reference only"
        string subject
        bigint originating_office_id FK
        bigint current_office_id FK
        string status
        string file_path
        bigint created_by FK
        bigint updated_by FK
        timestamp deleted_at
    }

    ROUTE_ACTIONS {
        bigint id PK
        bigint document_id FK
        bigint from_office_id FK
        bigint to_office_id FK
        string action
        text remarks
        bigint acted_by FK
        timestamp acted_at
    }

    ROUTES {
        bigint id PK
        string code
        string description
        boolean is_active
        bigint created_by FK
        bigint updated_by FK
        timestamp deleted_at
    }

    ROUTE_STEPS {
        bigint id PK
        bigint route_id FK
        bigint office_id FK
        smallint sequence
        json roles "reference label, e.g. [\"processing_staff\"]"
    }
```

`institution_id` on `OFFICES` and `USERS` is nullable (`nullOnDelete`) — an office/user can exist without an institution assigned. `DOCUMENT_TYPES` deliberately has **no** institution/office FK: document types are shared/global across the whole app, not scoped per institution.

`ROUTES` is a catalog of named, ordered office paths (`code` + `description`, with `ROUTE_STEPS` giving each one's office sequence and, optionally, the Shield role(s) expected to handle that step — `roles` is a documentation label only, never checked). A `DOCUMENTS` row may optionally point at one via `route_id`, set by its creator at registration. That link drives the "Process Flow" preview on the Create/Edit/View pages, and — once the document is routing — its Submit for Approval action: `DocumentRoutingService::submitForApprovalThroughRoute()` walks every remaining configured step in one call instead of Staff forwarding office-to-office by hand, and the manual Forward action is hidden for these documents. A `DOCUMENTS` row with no `route_id` (or an inactive/stepless route) is unaffected — Forward and a manual Submit-for-Approval office picker both work exactly as they did before this feature existed.

Roles/permissions (`document_originator`, `processing_staff`, `approver`, `super_admin`) are managed by Filament Shield's own `roles`, `permissions`, `model_has_roles` tables and are not modeled as a custom entity.

## 3. Process Flow Diagram

```mermaid
flowchart TD
    Start(["Start"]) --> Register["Originator registers document\nstatus = Registered"]
    Register --> Receive["Staff receives document\nstatus = In Routing"]
    Receive --> Forward{"Forward to\nApprover's office?"}
    Forward -- "No, another office first" --> Receive
    Forward -- "Yes" --> Submit["Staff submits for approval\nstatus = For Approval"]
    Submit --> Decision{"Approver decision"}
    Decision -- "Approve" --> Complete["status = Completed"]
    Decision -- "Return" --> Returned["status = Returned\nback to originating office\n(remarks required)"]
    Returned --> Resubmit["Originator resubmits\nstatus = In Routing"]
    Resubmit --> Receive
    Complete --> End(["End"])

    Rule["Business rule: a document has exactly one\ncurrent_office_id at any time, enforced with a\nrow lock so two simultaneous routing actions\ncan't both succeed"]
    Forward -.-> Rule
    Decision -.-> Rule
```

## 4. Data Flow Diagram — Level 0

```mermaid
flowchart LR
    Originator(["Document Originator"])
    Staff(["Processing Staff"])
    Approver(["Approver"])

    P1["1.0\nRegister Document"]
    P2["2.0\nRoute Document"]
    P3["3.0\nApprove / Return"]
    P4["4.0\nReport & Dashboard"]

    DS1[("Documents")]
    DS2[("Route Actions")]
    DS3[("Offices / Document Types")]

    Originator -- "document details" --> P1
    P1 -- "new document record" --> DS1
    P1 -- "reads types/offices" --> DS3

    Staff -- "receive/forward action" --> P2
    P2 -- "updates current_office_id, status" --> DS1
    P2 -- "logs action" --> DS2

    Approver -- "approve/return decision" --> P3
    P3 -- "updates status" --> DS1
    P3 -- "logs action" --> DS2

    DS1 --> P4
    DS2 --> P4
    P4 -- "dashboard counts, routing log, CSV export" --> Originator
    P4 -- "dashboard counts, routing log, CSV export" --> Staff
    P4 -- "dashboard counts, routing log, CSV export" --> Approver
```
