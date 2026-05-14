# Role-Based Access Control (RBAC) System

Leopardo RH employs a strict, hierarchal RBAC system to ensure data privacy and operational efficiency within each tenant.

## 👥 System Roles

| Role | Context | Primary Responsibility |
|------|---------|------------------------|
| **Super Admin** | Global | Platform governance, billing, and system health. |
| **Manager Principal** | Tenant | Full administrative control over the company. |
| **Manager HR** | Tenant | Employee lifecycle, absences, and documents. |
| **Manager Dept** | Tenant | Managing employees within a specific department. |
| **Manager Comptable** | Tenant | Payroll calculation and banking exports. |
| **Superviseur** | Tenant | Real-time attendance and task management for a team. |
| **Employee** | Tenant | Self-service: attendance, task updates, and payslips. |

## 🔑 Permission Matrix (High Level)

| Module | Principal | HR | Dept | Finance | Supervisor | Employee |
|--------|:---:|:---:|:---:|:---:|:---:|:---:|
| **Employees** | CRUD | CRUD | Read* | Read* | Read* | Self |
| **Attendance** | Full | Full | Dept | No | Team | Self |
| **Absences** | Approve | Approve | Dept | No | Team | Submit |
| **Payroll** | Full | Read | No | Full | No | Self |
| **Tasks** | Full | Full | Dept | No | Team | Self |

*\*Limited to non-sensitive fields.*

## 🛠 Technical Implementation

RBAC is enforced at multiple layers:

1. **Route Middleware:** Initial checks for `role` and `manager_role`.
2. **Eloquent Policies:** Fine-grained authorization logic for specific models and actions.
3. **Global Scopes:** Automatic data filtering (e.g., a Department Manager only sees employees where `department_id` matches their own).

### Policy Example
```php
public function view(Employee $user, Employee $target)
{
    if ($user->isManagerPrincipal() || $user->isManagerHR()) {
        return true;
    }

    if ($user->isManagerDept()) {
        return $user->department_id === $target->department_id;
    }

    return $user->id === $target->id;
}
```

---

For more details on security patterns, see [SECURITY.md](../../SECURITY.md).

The route-level audit matrix lives in [RBAC_ROUTE_MATRIX.md](RBAC_ROUTE_MATRIX.md) and must be updated whenever route guards or allowed roles change.
