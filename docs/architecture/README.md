# Architecture Documentation Hub

This directory contains in-depth documentation regarding the technical design and architectural principles of Leopardo RH.

## 🧭 Navigating the Architecture

| Component | Description |
|-----------|-------------|
| 🏗 **[General Architecture](../../ARCHITECTURE.md)** | Overview of the tech stack and high-level design. |
| 🌍 **[Multi-Tenancy](MULTITENANCY.md)** | Details on shared vs. physical isolation strategies. |
| 📊 **[Diagrams](DIAGRAMS.md)** | Visual representations of request flows and DB topology. |
| 🔒 **[RBAC System](../security/RBAC_SYSTEM.md)** | Role-based access control and permission matrix. |
| 🗄 **[Database Schema (ERD)](../dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md)** | Full entity-relationship diagram and field specs. |

## 📐 Design Principles

1. **Modular Monolith:** We keep everything in one repo for simplicity but enforce strict domain boundaries.
2. **PostgreSQL Search Path:** We leverage native DB features for tenant isolation instead of complex application-level filters where possible.
3. **API-First:** Every feature is built as an API endpoint first, then consumed by Web and Mobile clients.
4. **Encryption by Default:** Sensitive PII is always encrypted at rest.

---

For development instructions, please refer to the [Quick Start Guide](../../QUICKSTART.md).
