# Role-Based Access Control (RBAC) — Leopardo RH

Leopardo RH implements a granular RBAC system to ensure that users have exactly the permissions they need to perform their roles, and nothing more.

## 👥 User Roles

| Role | Description | Scope |
| :--- | :--- | :--- |
| **Platform Admin** | Super-admin for the entire SaaS platform. | Global (All Tenants) |
| **Company Owner** | Full control over a single tenant. | Tenant-wide |
| **HR Manager** | Manages employees, contracts, and payroll. | Tenant-wide |
| **Department Manager**| Manages a specific department and its team. | Department-only |
| **Employee** | Access to personal profile, attendance, and payslips. | Personal-only |
| **Supervisor** | View-only access for reporting and monitoring. | Assigned-only |

---

## 🏗 Authorization Layer

We use a combination of **Laravel Policies** and **Middlewares** to enforce RBAC.

### 1. Gatekeepers (Middlewares)
-   `auth:sanctum`: Ensures the user is authenticated.
-   `tenant`: Ensures the user is operating within their own data isolation.
-   `role:manager`: Restricts access to management features.

### 2. Business Rules (Policies)
Laravel Policies handle complex, record-level authorization.

**Example: Absence Approval**
```php
public function approve(User $user, Absence $absence)
{
    // Managers can only approve absences for their own team
    return $user->id === $absence->employee->manager_id
        || $user->hasRole('hr');
}
```

---

## 🛣 Route Access Matrix (Simplified)

| Endpoint | Employee | Dept Manager | HR Manager | Platform Admin |
| :--- | :---: | :---: | :---: | :---: |
| `GET /me/profile` | ✅ | ✅ | ✅ | ❌ |
| `POST /attendance/punch`| ✅ | ✅ | ✅ | ❌ |
| `GET /team/attendance` | ❌ | ✅ | ✅ | ❌ |
| `POST /payroll/calculate`| ❌ | ❌ | ✅ | ❌ |
| `GET /platform/billing` | ❌ | ❌ | ❌ | ✅ |

---

## 🔒 Security Principles
-   **Principle of Least Privilege:** Default access is "Deny All."
-   **Context-Aware:** Permissions change based on the tenant and department context.
-   **Auditable:** All permission changes are recorded in the system audit log.

---

For more security details, see [Security Policy](SECURITY.md).
