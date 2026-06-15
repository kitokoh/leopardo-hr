# Demo Accounts & Personas — Leopardo RH

To explore the platform's capabilities across different roles, you can use our built-in demo environment.

## 🚀 Instant Access

You can retrieve the latest demo credentials dynamically from our API:
`GET https://gestionemployerbackend.onrender.com/api/v1/demo-users`

---

## 👥 Persona Details

| Persona | Role | Primary Objective | Credentials |
| :--- | :--- | :--- | :--- |
| **System Admin** | Platform Admin | SaaS monitoring, Tenant creation. | `admin@leopardo-rh.com` / `password123` |
| **HR Director** | Company Owner | Full HR lifecycle, Payroll, Settings. | `hr@techcorp.com` / `password123` |
| **Dept Manager** | Manager | Team attendance, Absence approvals. | `manager@techcorp.com` / `password123` |
| **Field Worker** | Employee | Daily punch, Leave request, Payslips. | `employee@techcorp.com` / `password123` |

---

## 🛠 Usage Instructions

### 1. Web Dashboard
Navigate to [leo-admin.pages.dev](https://leo-admin.pages.dev) and use the **System Admin** persona to see the multi-tenant orchestration layer.

### 2. Mobile App
Download the APK from the [Visual Showcase](../../README.md#visual-showcase) section.
- Use **Employee** persona to test GPS-fenced check-in.
- Use **Manager** persona to approve the employee's request.

### 3. API Exploration
Use the **HR Director** token to explore the full REST API capabilities, including payroll calculation and reporting.

---

*Note: Data in the demo environment is reset every 24 hours. Do not store any sensitive or real data in these accounts.*
