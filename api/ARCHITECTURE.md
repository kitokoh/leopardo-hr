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
| `Modules/HR` | ✅ routes/modules/rh.php + hr_extended.php | ✅ complet | `HRServiceProvider` |
| `Modules/Payroll` | ✅ routes/modules/payroll_engine.php | ✅ complet | `PayrollServiceProvider` |
| `Modules/Attendance` | ✅ routes/modules/rh.php | ✅ complet | `AttendanceServiceProvider` |
| `Modules/Planning` | ✅ routes/modules/planning.php | ✅ complet | `PlanningServiceProvider` |
| `Modules/Absence` | ✅ routes/modules/absence.php | ✅ complet | `AbsenceServiceProvider` |
| `Modules/Expense` | ✅ routes/modules/expense.php | ✅ complet | `ExpenseServiceProvider` |
| `Modules/Notification` | ✅ routes/modules/notification.php | ✅ complet | `NotificationServiceProvider` |
| `Modules/Recruitment` | ✅ routes/modules/hr_extended.php | ✅ complet | `RecruitmentServiceProvider` |
| `Modules/Billing` | ✅ routes/modules/billing.php | ✅ complet | `BillingServiceProvider` |
| `Modules/Cabinet` | ✅ routes/modules/cabinet.php | ✅ complet | `CabinetServiceProvider` |
| `Modules/Fleet` | ✅ routes/modules/hr_extended.php | ✅ complet | `FleetServiceProvider` |
| `Modules/Cameras` | ✅ routes/modules/cameras.php | ✅ complet | `CamerasServiceProvider` |
| `Modules/Growth` | ✅ routes/modules/growth.php | ✅ complet | `GrowthServiceProvider` |
| `Modules/SmartAttendance` | ✅ module routes | ✅ complet | `SmartAttendanceServiceProvider` |
| `Modules/EdgeSync` | ✅ module routes | ✅ complet | `EdgeSyncServiceProvider` |
| `Modules/Onboarding` | ✅ routes/api.php | ✅ complet | `OnboardingServiceProvider` |
| `Modules/Platform` | ✅ routes/api.php | ✅ complet | `PlatformServiceProvider` |
| `Modules/Training` | ✅ routes/modules/* | ✅ complet | `TrainingServiceProvider` |

**Légende :** ✅ complet | 🔄 migration partielle en cours

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

## État du nettoyage (refactor/cleanup-legacy-api — juillet 2026)

### ✅ Fait

- **`app/Http/Controllers/Api/V1/`** — 90 controllers doublons supprimés.
  Restent intentionnellement : `EdgeController`, `EdgeDownloadController`, `SSO/SSOController`
  (pas encore de module dédié pour EdgeSync controllers et SSO).
- **`app/Services/` — 26 services doublons supprimés**, leurs imports redirigés
  vers `app/Modules/*/Infrastructure/Services/` dans tous les fichiers consommateurs.
- **`app/Modules/{Growth,Platform,Onboarding,Training}/Infrastructure/`** créé —
  couches manquantes ajoutées pour corriger le CI Module Structure Validator.
- Tests + Console Commands pointent sur les bons namespaces modules.

### 🔧 TODO restants (prochaines PRs)

#### Priorité haute

- [ ] **`app/Models/` — 75 modèles en double** avec `Modules/*/Domain/Models/`
  → Stratégie recommandée : créer des class aliases dans `app/Models/` pointant
  vers le module, puis basculer les imports progressivement par module.
  Fichiers sans doublon module à placer dans `Core/` ou nouveau module dédié :
  `Company`, `CompanyRequest`, `CompanySetting`, `Site`, `SuperAdmin` → `Core/Tenant/`
  `AuditLog`, `AIAuditLog`, `AIConversation`, `AIToolRegistryEntry` → `Core/` ou `app/AI/`
  `Notification`, `NotificationPreference`, `DeviceToken` → `Modules/Notification/Domain/Models/`
  `UserEmployeeLink` → `Modules/HR/Domain/Models/`
  `Commission` → `Modules/Payroll/Domain/Models/`
  `CommunicationEvent` → `Modules/Notification/Domain/Models/`
  `Language`, `PrivacyRequest` → `Core/` ou `Modules/HR/`

- [ ] **`app/DTOs/` racine — 3 DTOs** (`CheckInDTO`, `CreateEmployeeDTO`, `UpdateEmployeeDTO`)
  partiellement migrés mais encore sous namespace `App\DTOs`.
  → Mettre à jour `app/Modules/Attendance/Application/DTOs/CheckInDTO.php` pour
  utiliser le bon namespace, puis supprimer `app/DTOs/`.

#### Priorité moyenne

- [ ] **`app/Shared/` — peupler** avec `app/Traits/`, `app/Attributes/`, `app/Enums/`
  → 110+ usages de `App\Traits\BelongsToCompany` : migration par lots ou class aliases.

- [ ] **`Core/Tenant/`** — migrer `app/Services/TenantManager.php` + `TenantMiddleware`

- [ ] **`app/Http/Requests/Api/V1/`** — FormRequests encore dans le namespace legacy,
  à déplacer dans les modules correspondants (`Modules/*/Interfaces/Api/V1/Requests/`).

#### Priorité basse

- [ ] PHPStan modules : monter le niveau de 3 → 6 minimum pour Domain/Application
- [ ] Application layer : enrichir les Actions dans Growth, Platform, Onboarding, Training
  (trop peu d'Actions, controllers trop épais)

---

## Commandes utiles

```bash
# Vérifier qu'aucun controller legacy App\Http\Controllers\Api\V1 ne subsiste dans les routes
grep -r "App\\Http\\Controllers\\Api\\V1" api/routes/

# Vérifier qu'aucun service legacy App\Services\* ne subsiste hors app/Services/
grep -rn "App\\Services\\" api/app/ api/tests/ --include="*.php" | grep -v "app/Services/"

# Lancer PHPStan sur Core et Modules
vendor/bin/phpstan analyse app/Core app/Modules --level=3

# Vérifier que tous les ServiceProviders sont enregistrés
cat bootstrap/providers.php

# Vérifier la structure de modules DDD
for MOD in HR Payroll Attendance Planning Absence Expense Notification Recruitment Billing Cabinet Fleet Cameras Growth Platform Onboarding Training SmartAttendance EdgeSync; do
  for LAYER in Application Domain Infrastructure Interfaces Providers; do
    [ ! -d "app/Modules/$MOD/$LAYER" ] && echo "Missing: $MOD/$LAYER"
  done
done
```
