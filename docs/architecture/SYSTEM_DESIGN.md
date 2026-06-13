# System Design — Leopardo RH

Leopardo RH is architected as a **Modular Monolith** to provide the best balance between development velocity and system scalability. This document outlines the technical decisions and design patterns that make the platform "enterprise-ready."

## 🧩 Architectural Principles

1.  **Strict Multi-Tenancy:** Data isolation is enforced at the database layer (PostgreSQL schemas) to ensure zero cross-tenant data leakage.
2.  **Domain-Driven Design (DDD):** Business logic is encapsulated within specific domains (HR, Payroll, Attendance), reducing coupling.
3.  **API-First Approach:** All interfaces (Web, Mobile, Kiosk) consume a unified, versioned RESTful API.
4.  **Asynchronous Processing:** Long-running tasks like payroll generation and PDF rendering are handled via Redis-backed queues.

## 🧱 Layered Architecture

The backend (Laravel 11) is organized into four distinct layers:

### 1. Presentation Layer (Interfaces)
- **REST Controllers:** Handle HTTP requests and return JSON resources.
- **Console Commands:** CLI tools for system maintenance and scheduled tasks.

### 2. Application Layer (Use Cases)
- **Services:** Coordinate domain logic and infrastructure services.
- **DTOs (Data Transfer Objects):** Ensure type-safe data transfer between layers.
- **Events & Listeners:** Decouple side effects (e.g., sending a welcome email after employee creation).

### 3. Domain Layer (Core Business Logic)
- **Entities (Eloquent Models):** Rich models containing business rules.
- **Repositories:** Abstract data access logic.
- **Value Objects:** Objects defined by their attributes (e.g., Money, DateRange).

### 4. Infrastructure Layer
- **Database Migrations:** Schema versioning.
- **Third-party Integrations:** ZKTeco SDK, Firebase FCM, S3 Storage.

## 💾 Data Persistence Strategy

Leopardo RH uses **PostgreSQL 16** with a hybrid approach to multi-tenancy:

- **Public Schema:** Stores system-wide data (tenants, subscriptions, global settings).
- **Tenant Schemas:** Each tenant has its own isolated schema (e.g., `tenant_google`, `tenant_apple`).

### Scaling Strategy
- **Read/Write Splitting:** Ready for database clustering.
- **Redis Caching:** Aggressive caching of authorization rules and frequent lookups.

## 🤖 AI Orchestration Layer

The AI layer is designed to be provider-agnostic, currently leveraging LLMs for:
- **Anomaly Detection:** Identifying irregular attendance patterns.
- **Predictive Payroll:** Estimating future labor costs based on historical trends.
- **Automated Summaries:** Generating HR reports for management.

## 🔒 Security Architecture

- **Authentication:** Laravel Sanctum for token-based API authentication.
- **Authorization:** Fine-grained Role-Based Access Control (RBAC).
- **Audit Logs:** Every sensitive action is tracked in an immutable audit table.

---

*For implementation details, see:*
- [Architecture Diagram](ARCHITECTURE.md)
- [Multi-Tenancy Guide](MULTITENANCY.md)
- [API Reference](../api/README.md)
