# Security Policy — Leopardo RH

Leopardo RH is committed to the highest standards of data security and tenant isolation. As an enterprise HR platform, we handle sensitive personal and financial data, making security our top priority.

## 🛡️ Security Pillars

### 1. Multi-Tenant Isolation
Leopardo RH uses a hybrid isolation strategy to ensure data never leaks between companies:
- **Logical Isolation (Shared Mode):** Strict Row-Level Security enforced via Laravel Global Scopes and `company_id` mandatory fields.
- **Physical Isolation (Enterprise Mode):** Dedicated PostgreSQL schemas for every enterprise tenant. Isolation is enforced at the database level using `search_path`.

### 2. Data Encryption at Rest
Sensitive employee information is encrypted using **AES-256-CBC** before being stored in the database:
- **Encrypted Fields:** National ID, IBAN, Bank Account Number, Personal Contact Details.
- **Implementation:** Automated via Laravel's `EncryptedCast`. Even in the event of a database breach, this data remains unreadable without the application key.

### 3. Authentication & Session Management
- **Centralized Registry:** A global `user_lookups` table prevents email collisions and facilitates secure cross-tenant authentication routing.
- **Opaque Tokens:** We use Laravel Sanctum with opaque tokens for mobile and web clients, avoiding the risks associated with stateless JWTs (e.g., difficult revocation).
- **Session Revocation:** Administrators can instantly revoke sessions for specific devices or across the entire organization in case of compromise.
- **MFA:** Multi-Factor Authentication is mandatory for all Super Admin accounts.

### 4. Infrastructure Security
- **Secure Communication:** All data in transit is protected via TLS 1.3+.
- **Rate Limiting:** Aggressive rate limiting is applied to authentication and sensitive endpoints to prevent brute-force and DoS attacks.
- **Sanitization:** All inputs are strictly validated via Laravel FormRequests, and database queries are parameterized via Eloquent to prevent SQL injection.

## 🔍 Security Governance (Sentinel)

We maintain a dedicated security journal (`.jules/sentinel.md`) to track and mitigate architectural security gaps:
- **Safe Schema Switching:** All PostgreSQL `search_path` operations use whitelisted and quoted identifiers.
- **Global Uniqueness:** Email uniqueness is enforced globally across the platform to prevent identity hijacking.

## 🛡️ Role-Based Access Control (RBAC)

Leopardo RH implements a fine-grained RBAC system with 7 distinct levels:
- **Super Admin:** Platform management and billing.
- **Manager (Principal/HR/Dept/Comptable/Supervisor):** Specialized administrative access within a tenant.
- **Employee:** Restricted access to personal records and attendance tools.

For a detailed permission matrix, see [RBAC System Documentation](docs/security/RBAC_SYSTEM.md).

## 🚀 Reporting Vulnerabilities

If you discover a security vulnerability, please do not open a public issue. Instead, send an email to `security@leopardo-rh.com`. We aim to respond to all reports within 24 hours.
