# System Design & Modularity — Leopardo RH

## 🏰 The Modular Monolith Approach

Leopardo RH is architected as a **Modular Monolith**. This strategy provides the benefits of microservices (clear boundaries, domain isolation) with the operational simplicity of a single deployment unit.

### Why Modular Monolith?
-   **Clear Boundaries:** Each business domain is isolated, preventing "spaghetti code."
-   **Simplified Deployment:** One CI/CD pipeline, one unit of scale.
-   **Reduced Latency:** Domain communication happens in-process rather than over the network.
-   **Scalability:** Modules can be extracted into microservices if needed in the future.

---

## 🧩 Module Structure

Each module in `api/app/Modules` follows a standard internal structure:

```text
Module/
├── Controllers/      # API Endpoints
├── Models/           # Database Entities
├── Services/         # Business Logic Orchestration
├── Events/           # Domain Events (e.g., AbsenceRequested)
├── Listeners/        # Side-effect handlers (e.g., SendNotification)
├── Policies/         # RBAC Authorization
├── Resources/        # API Payload Transformations
└── Tests/            # Module-specific Feature Tests
```

---

## 🏛 Clean Architecture Layers

### 1. The Interface Layer (Blue)
-   **Controllers:** Handle HTTP requests and return JSON responses.
-   **Requests:** Validate incoming data using Laravel FormRequests.
-   **Resources:** Ensure consistent API shapes.

### 2. The Application Layer (Green)
-   **Services:** Execute business use cases. They are the only place where complex logic should live.
-   **Jobs:** Handle asynchronous tasks (e.g., generating PDF pay slips).

### 3. The Domain Layer (Yellow)
-   **Models:** Eloquent models with business rules.
-   **Scopes:** Multi-tenant and status-based filtering.
-   **Value Objects:** Complex types like `Money` or `Address`.

### 4. The Infrastructure Layer (Red)
-   **Repositories:** Abstraction for data access.
-   **Integrations:** Third-party SDKs (Stripe, ZKTeco, Sentry).

---

## 📡 Cross-Module Communication

Modules communicate via **Events** to maintain loose coupling.

**Example:**
1.  `AttendanceModule` records a check-in.
2.  It dispatches an `EmployeeCheckedIn` event.
3.  `PayrollModule` listens and updates the month's working hours.
4.  `NotificationModule` listens and pushes a notification to the Manager.

---

## 🛠 Tech Stack Rationales

-   **Laravel 11:** Robust ecosystem for rapid enterprise development.
-   **PostgreSQL 16:** Advanced JSONB support and schema-based multi-tenancy.
-   **Redis:** High-speed queue and cache management.
-   **Next.js 15:** Server-side rendering for optimal dashboard performance.
-   **Flutter:** Single codebase for native-performance mobile apps.

---

For more details, see [Architecture Overview](ARCHITECTURE.md).
