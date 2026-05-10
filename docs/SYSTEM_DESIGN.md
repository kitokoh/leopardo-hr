# System Design

Leopardo RH is designed as a **Modular Monolith** using Laravel 11. This choice provides the best balance between speed of development and architectural clarity.

## 📐 Design Philosophy

### 1. Separation of Concerns
We strictly separate the domain logic (Domain) from the application workflows (Application) and technical details (Infrastructure).

### 2. Multi-tenant Context
The system is built to be "tenant-blind" at the controller level. The context is automatically injected and enforced by the database layer.

### 3. API-First Strategy
The core engine is 100% API-driven. Web, Mobile, and Hardware clients consume the same unified JSON API.

## 🏗 High-Level Components

- **The Gateway (Laravel):** Handles routing, authentication, and request orchestration.
- **The Domain Modules:** Autonomous logic units (HR, Payroll, Attendance).
- **The Worker Layer:** Asynchronous processing for PDF generation and email delivery.
- **The Frontend (Next.js):** High-performance dashboard with server-side rendering.
- **The Mobile App (Flutter):** Native experience with real-time biometric capabilities.

## 🔄 Data Persistence
We use PostgreSQL for its robust schema management and multi-tenant capabilities (search_path). Redis is utilized for caching and session management.

---
See [ARCHITECTURE.md](ARCHITECTURE.md) for detailed diagrams.
