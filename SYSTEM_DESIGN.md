# Modular Monolith Design — Leopardo RH

Leopardo RH adopts a **Modular Monolith** architecture. This design pattern allows us to maintain a clean separation of business domains while avoiding the operational complexity of microservices.

## 📐 Structural Overview

The application is divided into autonomous modules, each owning its logic, data structure, and interfaces.

```text
api/app/
├── Models/             # Shared entities (Company, UserLookup)
├── Modules/            # Autonomous Domain Units
│   ├── HR/             # Employee, Contract, Department
│   ├── Attendance/     # Clock-in, Biometrics, Schedules
│   ├── Finance/        # Payroll, Advances, Expenses
│   └── Platform/       # Subscription, Billing, Health
├── Http/
│   ├── Controllers/    # Thin controllers delegating to Services
│   └── Middleware/     # Tenancy, RBAC, i18n
└── Services/           # Cross-module orchestration
```

## 🚀 Key Design Patterns

### 1. Automatic Tenant Context
Developers don't need to manually filter queries by `company_id`. The context is resolved by `TenantMiddleware` and applied globally via the `BelongsToCompany` trait.

### 2. Service-Layer Orchestration
Controllers are kept "thin." Business logic resides in **Service Classes**, which are injected via Laravel's Service Container. This ensures testability and reuse between Web and API routes.

### 3. Contract-Driven API
All API responses are transformed via **Eloquent Resources**. This decouples the internal database schema from the public API contract, allowing for schema refactoring without breaking mobile or third-party clients.

### 4. Event-Driven Decoupling
Modules communicate via **Laravel Events & Listeners**.
*Example:* When an `AttendanceRegistered` event is fired, the `Finance` module listens to update the daily payroll estimation asynchronously.

## 🔄 Data Architecture

Leopardo RH uses **PostgreSQL** as its primary engine, leveraging its advanced schema and JSON capabilities.

- **Relational Integrity:** Strict foreign keys and constraints.
- **Hybrid JSONB:** Used for `extra_data` and flexible configuration without schema migrations.
- **Search Path Isolation:** Used for Enterprise tenants to provide 100% database-level data separation.

## 🛠 Developer Workflow

- **Testing:** We prioritize **Feature Tests** using Pest PHP to verify end-to-end business flows with tenant isolation.
- **Documentation:** OpenAPI (Swagger) specs are kept in sync with the codebase in `api/openapi.yaml`.
- **Standards:** PSR-12 compliance and strict type hinting in PHP 8.4.

---

### See Also
- [High-Level Architecture](ARCHITECTURE.md)
- [Multi-Tenancy Strategy](docs/architecture/MULTITENANCY.md)
- [Database ERD](docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md)
