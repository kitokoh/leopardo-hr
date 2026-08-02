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
| `Modules/Absence` | ✅ routes/modules/absence.php | 🔶 Interfaces + Providers uniquement (derogation documentee, PA2-ARCH-002) | `AbsenceServiceProvider` |
| `Modules/Expense` | ✅ routes/modules/expense.php | 🔶 Interfaces + Providers uniquement (derogation documentee, PA2-ARCH-011) | `ExpenseServiceProvider` |
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

> **Derogation documentee — `Modules/Absence` (PA2-ARCH-002)** : ce module ne possede que `Interfaces/` (controllers `AbsenceController`/`LeavePolicyController` + Requests) et `Providers/`. Les couches `Domain/Application/Infrastructure` ont ete supprimees car elles dupliquaient integralement (memes colonnes, memes tables) les modeles/services reels du module `Planning` (`Planning\Domain\Models\{Absence,AbsenceType,LeaveBalance,LeaveBalanceLog}`, `Planning\Infrastructure\Services\AbsenceService`) : ceux-ci sont deja references par 100% des tests, controllers, events, resources, policies et seeders de conges/absences. `Planning` est desormais le seul proprietaire canonique des modeles d'absence ; `Modules/Absence` reste uniquement une facade HTTP (routes + controllers) qui consomme les classes `Planning\...` directement, en attendant une eventuelle extraction complete du domaine Absence hors de Planning.
>
> **Derogation documentee — `Modules/Expense` (PA2-ARCH-011, issue #1414)** : ce module ne possede que `Interfaces/` (controller `ExpenseClaimController`) et `Providers/`. Les couches `Domain/Application/Infrastructure` (`ExpenseClaim`, `ExpenseItem`, `ExpenseService`, `CreateExpenseClaim`, `SubmitExpenseClaim`, `ExpenseRepositoryInterface`, `CreateExpenseDTO`, `ExpenseNotDraftException`) ont ete supprimees car elles dupliquaient integralement (memes colonnes, meme table `expense_claims`/`expense_items`) le modele reel du module `Planning` (`Planning\Domain\Models\{ExpenseClaim,ExpenseItem}`) sans jamais etre appelees : le controller reellement route (`routes/modules/expense.php`) consommait deja `Planning\...\ExpenseClaim` directement, jamais `Expense\...\ExpenseClaim`. Consequence corrigee dans le meme changement : `AuthServiceProvider` enregistrait `Gate::policy()` sur le modele mort `Expense\Domain\Models\ExpenseClaim`, donc la policy Laravel native ne s'appliquait jamais au vrai modele utilise par le controller (celui-ci compensait avec des `abort_unless()` manuels). `Planning` est desormais le seul proprietaire canonique du modele de notes de frais ; `Modules/Expense` reste uniquement une facade HTTP qui consomme les classes `Planning\...` directement.

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
  `EdgeController`/`EdgeDownloadController` vivent désormais sous `Modules/EdgeSync/Interfaces/Api/V1/`.
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

- [x] PHPStan modules : monté le niveau de 3 → 5 (objectif réaliste). CI job
  `phpstan-modules` (bloquant) tourne sur `phpstan-modules.neon`.
- [ ] Application layer : enrichir les Actions dans Growth, Platform, Onboarding, Training
  (trop peu d'Actions, controllers trop épais)

### Trajectoire PHPStan (niveau actuel → cible)

_Issue #1413, complétant le suivi de la ligne ci-dessus._

| Config | Portée | Niveau actuel | Statut CI | Cible |
|---|---|---|---|---|
| `phpstan.neon` | tout `app/` | `max` | non branché à un job CI dédié (voir `phpstan-modules`/`phpstan-strict` ci-dessous) | n/a |
| `phpstan-modules.neon` | `app/Core`, `app/Modules`, `app/Shared` | `5` | **bloquant** (`architecture-check.yml`, job `phpstan-modules`) | rester à 5 pour l'instant ; réévaluer une montée à 6/7 une fois `phpstan-strict` stabilisé (voir ci-dessous) |
| `phpstan-strict.neon` | `app/Core`, `app/Modules`, `app/Shared` | `8` | **bloquant sur delta uniquement** depuis #1413 (`phpstan-strict-baseline.neon`, 2950 erreurs pré-existantes gelées ; `continue-on-error` retiré du job `phpstan-strict`) | réduire progressivement la baseline module par module (voir répartition ci-dessous), jamais l'augmenter |

**Répartition de la baseline `phpstan-strict` par module (2950 erreurs, 2026-08-01) :**

| Module | Erreurs |
|---|---|
| hors DDD (`app/Console`, `app/Http`, `app/AI`, `app/Enums`, `app/Exceptions`, ...) | 2220 |
| HR | 110 |
| Attendance | 108 |
| Payroll | 100 |
| Planning | 58 |
| EdgeSync | 50 |
| Billing | 48 |
| Core/Auth | 40 |
| SmartAttendance | 35 |
| Notification | 26 |
| Core/Feature | 25 |
| Platform | 22 |
| Cabinet / Shared | 15 chacun |
| Expense / Fleet | 12 chacun |
| Cameras | 10 |
| Growth | 9 |
| Recruitment / Training | 8 chacun |
| Onboarding | 7 |
| Marketing | 6 |
| Core/Tenant | 5 |
| Absence | 4 |

Convergence visée : `phpstan-modules` (5) et `phpstan-strict` (8) ne sont pas censés converger vers un seul niveau à court terme — `phpstan-modules` reste le gate rapide et bloquant sur tout PR touchant `app/Core`/`app/Modules`/`app/Shared`, tandis que `phpstan-strict` sert de garde anti-régression progressive sur le typage strict (niveau 8) sans bloquer sur la dette existante. Toute réduction de la baseline (fichier corrigé et retiré de `phpstan-strict-baseline.neon`) doit être faite module par module, dans des PR dédiées, pour rester revuable.

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

# Vérifier la structure de modules DDD (dynamique — voir aussi
# .github/workflows/architecture-check.yml, généré depuis app/Modules/* sans liste codée en dur)
# NB: Absence et Expense n'ont que Interfaces/+Providers/ (derogations PA2-ARCH-002
# et PA2-ARCH-011, voir plus haut)
FACADE_ONLY_MODULES="Absence Expense"
for MOD in $(ls app/Modules); do
  for LAYER in Application Domain Infrastructure Interfaces Providers; do
    if echo "$FACADE_ONLY_MODULES" | grep -qw "$MOD" && echo "Application Domain Infrastructure" | grep -qw "$LAYER"; then
      continue
    fi
    [ ! -d "app/Modules/$MOD/$LAYER" ] && echo "Missing: $MOD/$LAYER"
  done
done
for MOD in $FACADE_ONLY_MODULES; do
  for LAYER in Interfaces Providers; do
    [ ! -d "app/Modules/$MOD/$LAYER" ] && echo "Missing: $MOD/$LAYER"
  done
done
```
