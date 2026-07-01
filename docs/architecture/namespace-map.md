# Namespace Map — Architecture DDD (état final)

> **Migration terminée — juillet 2026**
> Les anciens namespaces `App\Http\Controllers\Api\V1\*` et `App\Services\*`
> n'existent plus dans le codebase. Ce document sert de référence historique
> et de guide de contribution pour tout nouveau code.

---

## Règle unique de contribution backend

> **Tout nouveau code métier va dans `api/app/Modules/<NomModule>/`.**
> `api/app/Http/Controllers/Api/V1/` est vide (sauf `EdgeController`, `EdgeDownloadController`, `SSO/`).
> `api/app/Services/` est vide (sauf les sous-dossiers non migrés : `Cache/`, `SSO/`, `Security/`, `Tracking/`, `Communication/`, `Payroll/`).
> `api/app/Models/` contient encore les modèles partagés en cours de migration vers `Domain/Models/`.

---

## Correspondance ancienne → nouvelle (référence)

### Core Auth

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\AuthController` | `App\Core\Auth\Interfaces\Api\V1\AuthController` |
| `App\Http\Controllers\Api\V1\PlatformAuthController` | `App\Core\Auth\Interfaces\Api\V1\PlatformAuthController` |
| `App\Services\AuthService` | `App\Core\Auth\Infrastructure\Services\AuthService` |
| `App\Models\Employee` (auth) | `App\Core\Auth\Domain\Models\Employee` |

### Module HR

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\EmployeeController` | `App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController` |
| `App\Http\Controllers\Api\V1\DepartmentController` | `App\Modules\HR\Interfaces\Api\V1\Controllers\DepartmentController` |
| `App\Services\EmployeeService` | `App\Modules\HR\Infrastructure\Services\EmployeeService` |
| `App\DTOs\CreateEmployeeDTO` | `App\Modules\HR\Application\DTOs\CreateEmployeeDTO` |

### Module Payroll

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\PayrollController` | `App\Modules\Payroll\Interfaces\Api\V1\PayrollController` |
| `App\Http\Controllers\Api\V1\PaySlipController` | `App\Modules\Payroll\Interfaces\Api\V1\PaySlipController` |
| `App\Services\PayrollService` | `App\Modules\Payroll\Infrastructure\Services\PayrollService` |

### Module Attendance

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\AttendanceController` | `App\Modules\Attendance\Interfaces\Api\V1\AttendanceController` |
| `App\Http\Controllers\Api\V1\KioskController` | `App\Modules\Attendance\Interfaces\Api\V1\KioskController` |
| `App\Services\AttendanceService` | `App\Modules\Attendance\Infrastructure\Services\AttendanceService` |
| `App\DTOs\CheckInDTO` | `App\Modules\Attendance\Application\DTOs\CheckInDTO` _(en cours)_ |

### Module Billing

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\BillingController` | `App\Modules\Billing\Interfaces\Api\V1\BillingController` |
| `App\Http\Controllers\Api\V1\StripeWebhookController` | `App\Modules\Billing\Interfaces\Api\V1\StripeWebhookController` |
| `App\Services\StripeService` | `App\Modules\Billing\Infrastructure\Services\StripeService` |

---

## Ce qui reste à migrer

Voir `api/ARCHITECTURE.md` section **"TODO restants"** pour la liste complète et priorisée.

Les points principaux :
- `api/app/Models/` — 75 modèles à déplacer dans `Modules/*/Domain/Models/` (migration progressive)
- `api/app/DTOs/` racine — 3 DTOs à finaliser
- `api/app/Services/{Cache,SSO,Security,Tracking,Communication,Payroll}/` — sous-dossiers spécialisés à décider
