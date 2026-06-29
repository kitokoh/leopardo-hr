# Architecture — leopardo-hr API
> Document de référence maintenu à jour. Toute déviation doit être discutée et documentée ici.

---

## Vision

L'API `leopardo-hr` suit un modèle **Domain-Driven Design (DDD) modulaire** :

- **`app/Core/`** — Socle transversal SaaS (Auth, Tenant)
- **`app/Modules/`** — Domaines métier autonomes (HR, Payroll, Attendance, etc.)
- **`app/Shared/`** — Kernel partagé entre modules (DTOs, Enums, Events, Exceptions, Traits)
- **`app/AI/`** — Module IA autonome (non DDD classique, orienté agent)

---

## Arbre de décision : où placer du code ?

```
Nouveau code à créer ?
│
├── Concerne Auth ou Tenant (multi-tenant) ?
│   └── → app/Core/{Auth|Tenant}/
│
├── Concerne un domaine métier (RH, Paie, Présences...) ?
│   └── → app/Modules/{NomModule}/
│       ├── Domain/Models/          Eloquent models du module
│       ├── Domain/Exceptions/      Exceptions métier
│       ├── Application/Actions/    Use Cases (1 action = 1 use case)
│       ├── Application/DTOs/       DTOs d'entrée des Actions
│       ├── Infrastructure/Services/ Services techniques (DB, files, emails...)
│       └── Interfaces/Api/V1/      Controllers + Requests HTTP
│
├── Partagé entre plusieurs modules (DTO générique, Enum commun...) ?
│   └── → app/Shared/{DTOs|Enums|Events|Exceptions|Traits}/
│
└── Lié à l'IA/LLM ?
    └── → app/AI/
```

---

## Règle des dépendances

```
Interfaces → Application → Domain
                         ↑
Infrastructure → Domain (only)
Shared ← (consommé par tout le monde, ne dépend de rien)
```

**Interdictions strictes :**
- Un module ne modifie jamais `Core/`
- Un module n'importe jamais directement les classes d'un autre module
  (passer par des Events Shared ou des contrats)
- `Domain/` ne connaît pas Laravel (pas de `use Illuminate\...` dans les Models DDD purs, sauf Eloquent)

---

## Modules existants

| Module | Statut routes | Statut code | ServiceProvider |
|--------|--------------|-------------|-----------------|
| `Core/Auth` | ✅ routes/api.php | ✅ complet | — (AppServiceProvider) |
| `Core/Tenant` | — | 🔄 en cours | — |
| `Modules/HR` | 🔄 routes/modules/rh.php + hr_extended.php | ✅ complet | `HRServiceProvider` |
| `Modules/Payroll` | 🔄 routes/modules/payroll_engine.php | ✅ complet | `PayrollServiceProvider` |
| `Modules/Attendance` | 🔄 routes/modules/rh.php | ✅ complet | `AttendanceServiceProvider` |
| `Modules/Planning` | 🔄 routes/modules/planning.php | ✅ complet | `PlanningServiceProvider` |
| `Modules/Absence` | ✅ routes/modules/absence.php | ✅ complet | `AbsenceServiceProvider` |
| `Modules/Expense` | ✅ routes/modules/expense.php | ✅ complet | `ExpenseServiceProvider` |
| `Modules/Notification` | ✅ routes/modules/notification.php | ✅ complet | `NotificationServiceProvider` |
| `Modules/Recruitment` | 🔄 routes/modules/hr_extended.php | ✅ complet | `RecruitmentServiceProvider` |
| `Modules/Billing` | 🔄 routes/modules/billing.php | ✅ complet | `BillingServiceProvider` |
| `Modules/Cabinet` | 🔄 routes/modules/cabinet.php | ✅ complet | `CabinetServiceProvider` |
| `Modules/Fleet` | 🔄 routes/modules/hr_extended.php? | ✅ complet | `FleetServiceProvider` |
| `Modules/Cameras` | ✅ routes/modules/cameras.php | ✅ complet | `CamerasServiceProvider` |

**Légende :** ✅ migré vers nouveau namespace | 🔄 migration partielle en cours

---

## Conventions de nommage

### Actions (Use Cases)
- Nom : verbe + sujet → `LoginAction`, `CreateEmployeeAction`, `ValidatePayrollAction`
- 1 action = 1 `execute()` method
- Injectées dans les controllers, jamais instanciées avec `new`

### Controllers
- Slim : délèguent à des Actions, ne contiennent pas de logique métier
- Retournent toujours `JsonResponse`
- Utilisent des `FormRequest` pour la validation

### Models (Domain)
- Eloquent est accepté dans `Domain/Models/` (pragmatisme Laravel)
- Pas de `$fillable = ['*']` — lister explicitement les champs

### ServiceProviders
- Chaque module enregistre ses bindings dans son propre ServiceProvider
- Enregistré dans `bootstrap/providers.php`

---

## Migration en cours (ancienne → nouvelle archi)

### Controllers routes — migration complète ✅

Tous les `use App\Http\Controllers\Api\V1\*` ont été remplacés dans les fichiers de routes.

| Controller | Nouveau namespace |
|-----------|------------------|
| `MeController` | `Modules\HR\Interfaces\Api\V1\Controllers\` |
| `SiteController` | `Modules\HR\Interfaces\Api\V1\Controllers\` |
| `EstimationController` | `Modules\Payroll\Interfaces\Api\V1\` |
| `NotificationStreamController` | `Modules\Notification\Interfaces\Api\V1\Controllers\` |
| `AdvancedReportController` | `Modules\HR\Interfaces\Api\V1\Controllers\` |
| `AuditLogController` | `Modules\HR\Interfaces\Api\V1\Controllers\` |
| `EmployeeLoanController` | `Modules\Payroll\Interfaces\Api\V1\` |
| `PredictionController` | `Modules\HR\Interfaces\Api\V1\Controllers\` |

### TODO restants

- [ ] Vider `app/Models/` des modèles déjà migrés dans `Modules/*/Domain/Models/`
- [ ] Vider `app/Services/` des services déjà migrés dans `Modules/*/Infrastructure/Services/`
- [ ] Peupler `app/Shared/` et supprimer les doublons `app/DTOs/`, `app/Enums/`, `app/Traits/`
- [ ] Implémenter `Core/Tenant/` (migrer `TenantManager` + `TenantMiddleware`)

---

## Commandes utiles

```bash
# Vérifier les namespaces dans les routes
grep -r "App\\Http\\Controllers\\Api\\V1" api/routes/

# Lancer PHPStan sur Core et Modules
vendor/bin/phpstan analyse app/Core app/Modules --level=8

# Vérifier que tous les ServiceProviders sont enregistrés
cat bootstrap/providers.php
```
