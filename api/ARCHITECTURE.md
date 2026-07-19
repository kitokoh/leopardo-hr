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
| `Modules/Marketing` | ✅ routes/modules/marketing.php | ✅ complet | `MarketingServiceProvider` |
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

- [x] **`app/Models/`** — 75 aliases existants (commit 807d6f09) + 17 modèles orphelins
  placés dans leurs modules (PR phase2) : `Company/Site/CompanyRequest/CompanySetting/SuperAdmin` → `Core/Tenant/Domain/Models/`,
  `AuditLog` → `Core/Auth/Domain/Models/`, `Language` → `Shared/Models/`,
  `UserEmployeeLink/PrivacyRequest` → `HR/Domain/Models/`, `Commission` → `Payroll/Domain/Models/`,
  `Notification/NotificationPreference/DeviceToken/CommunicationEvent` → `Notification/Domain/Models/`,
  `AIAuditLog/AIConversation/AIToolRegistryEntry` → `app/AI/Models/`.
  Aliases backward-compat dans `app/Models/` pour zéro breaking change.

- [x] **`app/DTOs/` racine** — Supprimé. `CheckInDTO` → `App\Modules\Attendance\Application\DTOs`,
  `CreateEmployeeDTO` / `UpdateEmployeeDTO` → `App\Modules\HR\Application\DTOs`. (PR #824 suite)

#### Priorité moyenne

- [x] **`app/Shared/`** — peuplé (commit 807d6f09) : `Shared/Traits/`, `Shared/Attributes/`, `Shared/Enums/`.
  `app/Traits/`, `app/Attributes/`, `app/Enums/` sont des shims. Canonical dans `Shared/`.

- [x] **`Core/Tenant/`** — `TenantManager` migré dans `App\Core\Tenant\TenantManager` (commit 807d6f09).
  `app/Services/TenantManager.php` est un alias shim.

- [x] **`app/Http/Requests/`** — 64 FormRequests copiés dans leurs modules respectifs.
  Shims backward-compat maintenus dans `app/Http/Requests/` pour les usages existants.
  22 fichiers consommateurs mis à jour avec nouveaux namespaces.

#### Priorité basse

- [ ] PHPStan modules : monter le niveau de 3 → 5 (objectif réaliste)
- [ ] Application layer : enrichir les Actions dans Growth, Platform, Onboarding, Training
  (trop peu d'Actions, controllers trop épais)

### ✅ Nettoyage complet — bilan cumulé (PR #824 + phase2)

| Supprimé / Migré | Quantité |
|---|---|
| Controllers `app/Http/Controllers/Api/V1/` supprimés | 90 |
| Services `app/Services/` doublons supprimés | 26 |
| Services `app/Services/` non-doublons migrés + shimmed | 13 |
| Modèles `app/Models/` convertis en aliases | 92 (75+17) |
| FormRequests migrés vers modules | 64 |
| `app/Shared/` peuplé (Traits, Attributes, Enums) | ✅ |
| `Core/Tenant/TenantManager` canonique | ✅ |
| `app/DTOs/` supprimé | ✅ |

**`app/Services/`** : reste uniquement TenantManager.php (shim) + sous-dossiers `Cache/`, `Communication/`, `Payroll/`, `SSO/`, `Security/`, `Tracking/` (services spécialisés à migrer si besoin).
**`app/Http/Requests/`** : shims backward-compat. Canonical dans les modules.

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
