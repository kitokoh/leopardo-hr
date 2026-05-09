# Security & Compliance — Leopardo RH

Leopardo RH is built on the principle of **Zero Trust** for data isolation. As an enterprise HR SaaS handling sensitive PII (Personally Identifiable Information) and financial data, security is not a feature—it is the foundation.

## 🛡️ The Security Model

### 1. Multi-Tenant Guardrails
Isolation is enforced at the lowest possible level to prevent cross-tenant data leaks (IDORs):
- **Shared Tenants:** Every query is automatically scoped via the `BelongsToCompany` trait.
- **Enterprise Tenants:** Physical isolation via dedicated PostgreSQL schemas.
- **Global Registry:** Authenticated users are verified against a global identity registry before any tenant-specific context is loaded.

### 2. Encryption & Data Privacy
- **At Rest:** Sensitive fields (National IDs, IBANs, Home Addresses) are encrypted using **AES-256-GCM**.
- **In Transit:** Mandatory TLS 1.3 for all API communication.
- **Key Rotation:** Support for per-tenant encryption keys for Enterprise customers.

### 3. Identity & Access Management (IAM)
- **Sanctum Authentication:** Secure, stateful tokens for web and mobile clients.
- **Strict RBAC:** 7 hierarchal roles (from Employee to Principal Manager) with granular permission checks.
- **Session Governance:** Real-time session monitoring and instant revocation capabilities for IT administrators.

## 👥 Role-Based Access Control (RBAC)

The platform uses a layered RBAC approach. For a detailed breakdown of permissions, see the [RBAC Matrix](docs/security/RBAC_SYSTEM.md).

| Role | Access Level | Context |
|------|--------------|---------|
| **Super Admin** | System | Platform-wide (Billing, Infrastructure, Global Settings) |
| **Manager Principal** | Tenant | Full Ownership (Financials, Settings, ALL Data) |
| **Manager HR** | Tenant | Operational HR (Employee lifecycle, Documents) |
| **Manager Dept** | Tenant | Departmental (Restricted to their own team) |
| **Employee** | Personal | Self-Service (My Attendance, My Payslips) |

## 🛡️ Infrastructure Hardening

- **Rate Limiting:** IP-based and User-based throttling on all sensitive endpoints (Auth, Exports).
- **Audit Logging:** Immutible logs for all administrative actions (Who changed what, and when).
- **SQL Injection Prevention:** 100% Eloquent/Query Builder coverage with no raw query usage for tenant data.
- **Environment Protection:** Automated dependency scanning (Dependabot) and SAST (CodeQL).

## 🌍 Compliance Standards

Leopardo RH is designed to be compliant with:
- **GDPR (EU):** Right to erasure, data portability, and strict processing logs.
- **Loi 18-07 (Algeria):** Protection of personal data for Algerian entities.
- **Loi 09-08 (Morocco):** Protection of individuals with regard to the processing of personal data.

## 🚀 Reporting a Vulnerability

We value the work of security researchers. If you find a vulnerability, please report it via `security@leopardo-rh.com`. We commit to:
1. Acknowledging your report within 12 hours.
2. Providing a fix for P0 vulnerabilities within 48 hours.
3. Keeping you updated throughout the remediation process.

---

### Internal Security Resources
- [RBAC System Deep-Dive](docs/security/RBAC_SYSTEM.md)
- [Multi-Tenancy Strategy](docs/architecture/MULTITENANCY.md)
- [Security Testing Protocol](docs/testing/REGRESSION_SUITE.md)
