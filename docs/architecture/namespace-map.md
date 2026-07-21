# Namespace Map — Architecture DDD (état final)

> **Migration terminée — juillet 2026**
> Les anciens namespaces `App\Http\Controllers\Api\V1\*` et `App\Services\*`
> n'existent plus dans le codebase. Ce document sert de référence historique
> et de guide de contribution pour tout nouveau code.

---

## Règle unique de contribution backend

> **Tout nouveau code métier va dans `api/app/Modules/<NomModule>/`.**
> `api/app/Http/Controllers/Api/V1/` n'existe plus du tout (`EdgeController`/`EdgeDownloadController`
> vivent désormais dans `Modules/EdgeSync/Interfaces/Api/V1/`).
> `api/app/Services/` ne contient plus que le shim `TenantManager.php` (`@deprecated`, alias vers
> `App\Core\Tenant\TenantManager`) et des sous-dossiers de services spécialisés non encore migrés :
> `Cache/`, `SSO/`, `Security/`, `Tracking/`, `Communication/`, `Payroll/`.
> `api/app/Models/` n'existe plus du tout — tous les modèles vivent sous `Modules/<Name>/Domain/Models/`
> ou `Core/<Name>/Domain/Models/`.

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
| `App\DTOs\CheckInDTO` | `App\Modules\Attendance\Application\DTOs\CheckInDTO` |

### Module Billing

| Ancien (supprimé) | Actuel |
|---|---|
| `App\Http\Controllers\Api\V1\BillingController` | `App\Modules\Billing\Interfaces\Api\V1\BillingController` |
| `App\Http\Controllers\Api\V1\StripeWebhookController` | `App\Modules\Billing\Interfaces\Api\V1\StripeWebhookController` |
| `App\Services\StripeService` | `App\Modules\Billing\Infrastructure\Services\StripeService` |

---

## Ce qui reste à migrer

Voir `api/ARCHITECTURE.md` section **"TODO restants"** pour la liste complète et priorisée.

`api/app/Models/` et `api/app/DTOs/` racine sont déjà entièrement supprimés (voir bilan «Nettoyage
complet» dans `api/ARCHITECTURE.md`). Ce qui reste réellement en coexistence :
- `api/app/Services/{Cache,SSO,Security,Tracking,Communication,Payroll}/` — sous-dossiers spécialisés à décider (migrer ou garder)
- `api/app/Exceptions/` — base `DomainException` partagée, encore étendue par certains modules
