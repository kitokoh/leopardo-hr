# Demo Accounts & Personas — Leopardo RH

To explore the platform's capabilities across different roles, you can use the
built-in demo environment.

## 🚀 Instant Access

The canonical, always-fresh source of demo credentials is the API itself:

```
GET https://gestionemployerbackend.onrender.com/api/v1/demo-users
```

> **This endpoint only returns data when demo mode is explicitly enabled**
> (`DEMO_MODE_ENABLED=true` on the backend). If demo mode is off — which is the
> default, including on the production Render deployment today — the endpoint
> returns `404 RESOURCE_NOT_FOUND` on purpose, so it never leaks real-looking
> credentials in an environment where demo mode was not opted into. See
> `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/DemoUserController.php`
> and `docs/security/AUDIT_API_2026-07-19.md` (section 1) for the rationale.
>
> The credentials below reflect the same seeded personas (see
> `api/database/seeders/DemoCompanyOnceSeeder.php`) used by the API response and
> by `docs/DEMARRAGE_RAPIDE.md`, so this table stays useful for local/staging
> environments where `DEMO_MODE_ENABLED=true`, or once a public demo/staging
> environment with demo mode on is available.

---

## 👥 Persona Details (TechCorp Algerie SARL — `techcorp-algerie`)

All demo accounts share the same password: `password123`.

| Persona | Role | `manager_role` | Primary Objective | Credentials |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | Platform Admin | — | SaaS monitoring, tenant creation, plans/requests. | `admin@leopardo-rh.com` |
| **Manager Principal** | Manager | `principal` | Executive dashboard, launch readiness, payroll/exports. | `ahmed.benali@techcorp-algerie.dz` |
| **Manager RH** | Manager | `rh` | Employees, absences, HR onboarding, communication analytics. | `fatima.meziane@techcorp-algerie.dz` |
| **Manager Departement** | Manager | `dept` | Department team, team absences, projects/tasks. | `samir.boukhalfa@techcorp-algerie.dz` |
| **Manager Comptable** | Manager | `comptable` | Payroll, bank exports, HR financial follow-up. | `lina.haddad@techcorp-algerie.dz` |
| **Manager Superviseur** | Manager | `superviseur` | Field attendance, kiosk, biometric enrolment requests. | `nassim.cheriet@techcorp-algerie.dz` |
| **Employee** | Employee | — | Employee self-service, mobile punch, notifications/absences. | `karim.aouad@techcorp-algerie.dz` |

Two more demo companies are seeded with a lighter persona set, useful for
testing country/currency variations (Morocco `pharmaplus-casablanca`, Tunisia
`digitalflow-tunis`) — see the full response of `/api/v1/demo-users` for their
exact accounts.

---

## 🛠 Usage Instructions

### 1. Web Dashboard
Navigate to [leo-admin.pages.dev](https://leo-admin.pages.dev) and use the
**Super Admin** persona to see the multi-tenant orchestration layer, or
[gestionemployer-backend.vercel.app](https://gestionemployer-backend.vercel.app/)
for the manager/employee web experience.

### 2. Mobile App
Download the APK from the [Visual Showcase](../README.md#-visual-showcase) section.
- Use the **Employee** persona to test GPS-fenced check-in.
- Use a **Manager** persona to approve the employee's request.

### 3. API Exploration
Use a **Manager Principal** token to explore the full REST API capabilities,
including payroll calculation and reporting. See `docs/api/API_REFERENCE.md`
for the login flow.

---

*Note: demo/staging data may be reset periodically depending on the
environment. Do not store any sensitive or real data in these accounts.*
