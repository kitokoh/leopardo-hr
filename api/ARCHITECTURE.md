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

**Garde CI (issue #5584, audit architecture 2026-08-26) :**
`dev-hub/tools/check-module-isolation.sh` (branché dans `architecture-check.yml`,
job Module Structure Validator) bloque TOUT nouvel import croisé :
- `use App\Modules\<X>\...` dans un module ≠ X ;
- `use App\Modules\<X>\...` dans `Core/`.

La dette héritée (20 modules sources, 55 paires source→cible — sortie de la
garde le 2026-09-05 ; +4 paires justifiées le 2026-09-04 via #6816) est **actée** dans
`dev-hub/tools/module-isolation-allowlist.txt` et doit **diminuer** : toute
ligne ajoutée exige une justification (issue de refactor), aucune n'est
rajoutée « pour passer ». Dérogations structurelles déjà documentées :
`Modules/Absence` est une façade sur `Planning` ; `Modules/Expense` est un module DDD partiel depuis #5235 (couche `Application/` absente — voir dérogation PA2-ARCH-011, à jour plus bas)
(PA2-ARCH-002 / PA2-ARCH-011). Refactors de résorption en cours : #5591
(PayrollCalculator → extraire `Planning`) ; déplacement de l'`Employee`
canonique hors de `Core/Auth` (#5584 item 3, chantier à part — 361
consommateurs).

**Frontières du module CRM client (issue #5745, ADR 0018) :** le futur module
`CRM` (CRM client tenant, cf. ADR-CRM-DUAL-CONTEXTS) est couvert par une garde
orientée dédiée, `dev-hub/tools/check-crm-boundary-imports.sh`, en complément
de la garde symétrique #5584 ci-dessus. Elle interdit en HARD BLOCK tout
`use App\Modules\{Platform,Marketing,Payroll,Accounting}\` dans
`api/app/Modules/CRM/**` (CRM commercial, paie, comptabilité — aucun échange
direct), et exige une exemption justifiée dans
`dev-hub/tools/crm-boundary-allowlist.txt` pour tout autre import
inter-module. Les imports `App\Core\*`, `App\Shared\*` et intra-module
restent libres. La garde est en veille tant que `Modules/CRM` n'existe pas.

| Module | Statut routes | Statut code | ServiceProvider |
|--------|--------------|-------------|-----------------|
| `Core/Auth` | ✅ routes/api.php | ✅ complet | — (AppServiceProvider) |
| `Core/Tenant` | — | ✅ migré (TenantManager canonique) | — |
| `Modules/HR` | ✅ routes/modules/rh.php + hr_extended.php | ✅ complet | `HRServiceProvider` |
| `Modules/Payroll` | ✅ routes/modules/payroll_engine.php | ✅ complet | `PayrollServiceProvider` |
| `Modules/Attendance` | ✅ routes/modules/rh.php | ✅ complet | `AttendanceServiceProvider` |
| `Modules/Planning` | ✅ routes/modules/planning.php | ✅ complet | `PlanningServiceProvider` |
| `Modules/Absence` | ✅ routes/modules/absence.php | 🔶 Interfaces + Providers uniquement (derogation documentee, PA2-ARCH-002) | `AbsenceServiceProvider` |
| `Modules/Expense` | ✅ routes/modules/expense.php | 🟡 partiel depuis #5235 — Domain/Infrastructure/Interfaces/Providers présents ; **Application/ absente** (chantier ouvert 2026-09-05) ; modèles de notes de frais sous contrat `Planning` | `ExpenseServiceProvider` |
| `Modules/Notification` | ✅ routes/api.php + dashboard.php + hr_extended.php | ✅ complet | `NotificationServiceProvider` |
| `Modules/Recruitment` | ✅ routes/modules/hr_extended.php | ✅ complet | `RecruitmentServiceProvider` |
| `Modules/EduManager` | ✅ routes/modules/edu_manager.php | 🟢 verticale BC-16 (EDU-001..020, core + batch2 + batch3) | `EduManagerServiceProvider` |
| `Modules/RestaurantManager` | ✅ routes/modules/restaurantmanager.php | 🟢 verticale BC-25 (Application/Domain/Infrastructure/Interfaces/Providers) | `RestaurantManagerServiceProvider` |
| `Modules/Restaurant` | — (pas de routes propres : webhooks livraison publics et shop inline dans `routes/api.php` via `RestaurantManager` ; surveys publics via `Core\Solutions` / `routes/modules/solutions.php`) | 🔶 fournisseur de contenu Solutions : Domain (`Domain/Solution`, `Domain/Survey`) + Providers ; Application/Infrastructure/Interfaces = squelettes `.gitkeep` | `RestaurantServiceProvider` |
| `Modules/Billing` | ✅ routes/modules/billing.php | ✅ complet | `BillingServiceProvider` |
| `Modules/Cabinet` | ✅ routes/modules/cabinet.php | ✅ complet | `CabinetServiceProvider` |
| `Modules/Fleet` | ✅ routes/modules/hr_extended.php | ✅ complet | `FleetServiceProvider` |
| `Modules/Cameras` | ✅ routes/modules/cameras.php | ✅ complet | `CamerasServiceProvider` |
| `Modules/CRM` | ✅ routes/modules/crm.php | ✅ complet (CRM client, ADR-CRM-DUAL-CONTEXTS) | `CrmServiceProvider` |
| `Modules/FuelStation` | ✅ routes/modules/fuel_station.php | ✅ DDD complet (Application/Domain/Infrastructure/Interfaces/Providers) | `FuelStationServiceProvider` |
| `Modules/Delivery` | ✅ routes/modules/delivery.php | 🟢 verticale BC-26 consolidée (#6757, PHPStan assaini #6759) | `DeliveryServiceProvider` |
| `Modules/EdgeSync` | ✅ module routes | ✅ complet | `EdgeSyncServiceProvider` |
| `Modules/TravelAgency` | ✅ routes partagées + publiques shop | 🟢 fondations verticale BC-24 (TRAVEL-101..108, 201..203 + shop/e-billets) | `TravelAgencyServiceProvider` |
| `Modules/Growth` | ✅ routes/modules/growth.php | ✅ complet | `GrowthServiceProvider` |
| `Modules/Marketing` | ✅ routes/modules/marketing.php | ✅ complet | `MarketingServiceProvider` |
| `Modules/Onboarding` | ✅ routes/api.php | ✅ complet | `OnboardingServiceProvider` |
| `Modules/Platform` | ✅ routes/api.php | ✅ complet | `PlatformServiceProvider` |
| `Modules/Accounting` | ✅ routes/modules/accounting.php | ✅ complet | `AccountingServiceProvider` |

> **Derogation documentee — API Resources centralisees (PA2-ARCH-010)** : contrairement au schema DDD par module (`Modules/<Nom>/Interfaces/Api/V1/Resources/`) documente en §2.7 de `CONVENTIONS.md` et repris par le stub `stubs/module-template/`, les **67 classes `JsonResource`** (dénombrées le 2026-09-05) de l'API vivent toutes dans `app/Http/Resources/Api/V1/` (namespace legacy centralise) et sont importees a la piece par ~50 controllers de modules differents. C'est un choix delibere, pas une migration inachevee : plusieurs Resources sont **partagees entre modules** (ex. `LoanResource` par `HR` et `Payroll`, `AttendanceTodayResource` par `Attendance` et `HR`, `PayrollRunResource`, `VehicleAlertResource`/`VehicleMaintenanceResource`/`VehicleTripResource` par `Fleet` depuis plusieurs controllers, `InterviewResource`/`JobPostingResource`/`ApplicantResource` par `Recruitment` depuis plusieurs controllers). Deplacer chaque Resource dans le module qui l'a cree obligerait les autres modules consommateurs a l'importer inter-module — ce qui viole l'interdiction stricte ci-dessus (« un module n'importe jamais directement les classes d'un autre module »). Garder les Resources centralisees dans `app/Http/Resources/Api/V1/` evite ce couplage inter-module et reste coherent avec `App\Shared\*` (namespace commun consomme par tout le monde, ne dependant de rien). Le template `stubs/module-template/Interfaces/Api/V1/Resources/` et `docs/architecture/module-creation-guide.md` ont ete corriges pour ne plus induire les nouveaux modules en erreur (voir PA2-ARCH-010) : les Resources d'un nouveau module vont dans `app/Http/Resources/Api/V1/` sauf si elles sont strictement internes et jamais partagees, auquel cas elles peuvent rester dans `Interfaces/Api/V1/Resources/` du module.

**Légende :** ✅ complet | 🟢 verticale consolidée/complétée via le merge 2026-09-02 | 🔶 partiel / en cours | 🔄 migration partielle en cours

> **Note de complétude (2026-09-05, audit PM)** : le validateur CI (Module
> Structure Validator) contrôle l'existence des 5 couches racines
> (`Application`/`Domain`/`Infrastructure`/`Interfaces`/`Providers`) ; il ne
> garantit pas que les sous-couches internes (`Actions`, `DTOs`,
> `Domain/Models`…) sont peuplées. Mesure réelle par module et par sous-couche :
> `docs/ARCHITECTURE_STATUS.md` (table reconstruite le 2026-09-05). Résumé :
> 8 modules ont les 8 sous-couches canoniques peuplées (Accounting, Attendance,
> Cabinet, CRM, HR, Marketing, Notification, Platform) ; la plupart des autres
> modules (dont les verticales récentes Delivery, EduManager, FuelStation,
> Delivery, EduManager, FuelStation n'ont ni Actions ni DTOs ; RestaurantManager
> (22 Actions) et TravelAgency (35 Actions) ont des Actions mais pas de
> `Application/DTOs` ; `Cameras` garde ses modèles Eloquent à la racine de `Domain/`
> (pas de `Domain/Models`).

> **Derogation documentee — `Modules/Absence` (PA2-ARCH-002)** : ce module ne possede que `Interfaces/` (controllers `AbsenceController`/`LeavePolicyController` + Requests) et `Providers/`. Les couches `Domain/Application/Infrastructure` ont ete supprimees car elles dupliquaient integralement (memes colonnes, memes tables) les modeles/services reels du module `Planning` (`Planning\Domain\Models\{Absence,AbsenceType,LeaveBalance,LeaveBalanceLog}`, `Planning\Infrastructure\Services\AbsenceService`) : ceux-ci sont deja references par 100% des tests, controllers, events, resources, policies et seeders de conges/absences. `Planning` est desormais le seul proprietaire canonique des modeles d'absence ; `Modules/Absence` reste uniquement une facade HTTP (routes + controllers) qui consomme les classes `Planning\...` directement, en attendant une eventuelle extraction complete du domaine Absence hors de Planning.
>
> **Historique — `Modules/Expense` (PA2-ARCH-011, issue #1414, évolution #5235)** : la dérogation initiale (2024-2026) documentait un module « façade HTTP » ne possédant que `Interfaces/` (`ExpenseClaimController`) et `Providers/`, les modèles métier (`ExpenseClaim`, `ExpenseItem`) vivant canoniquement dans `Planning\Domain\Models` (le controller routé consommait `Planning\...\ExpenseClaim` directement ; la policy enregistrée sur le modèle mort avait été corrigée).
> **État actuel (depuis #5235)** : `Modules/Expense` est un module DDD **partiel** — la couche `Application/` n'existe pas (constat 2026-09-05, chantier ouvert). Présents : `Domain/Models/ExpenseAccountingEntry`, `Domain/Exceptions/UnbalancedExpenseEntriesException`, `Infrastructure/{Listeners,Services}` (écritures comptables des notes de frais, flux Expense → Accounting) et `Interfaces/Api/V1/Controllers/ExpenseAccountingController` (routes `/expense-claims/{id}/accounting-entries` actives). La dérogation PA2-ARCH-011 ne s'applique plus à Expense : seuls les modèles de *notes de frais* restent sous contrat `Planning` (propriétaire canonique historique).

---

> **Dérogation documentée — `app/Http/Controllers/Web/` (surface session, audit #6578)** : les
> contrôleurs `Web/*` (KioskController, DashboardController, InvitationController,
> PlatformAuthController…) constituent la surface **session/blade** (portail Laravel),
> distincte de l'API JSON par module — basenames partagés avec des modules volontairement
> (surface produit différente), placement conservé et acté.
>
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
- **`app/Modules/{Growth,Platform,Onboarding}/Infrastructure/`** créé —
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
  L'alias shim `app/Services/TenantManager.php` a été supprimé (issue #1494 puis #1728).

- [x] **`app/Http/Requests/`** — 64 FormRequests copiés dans leurs modules respectifs.
  Shims backward-compat maintenus dans `app/Http/Requests/` pour les usages existants.
  22 fichiers consommateurs mis à jour avec nouveaux namespaces.

#### Priorité basse

- [x] PHPStan modules : monté le niveau de 3 → 5 (objectif réaliste). CI job
  `phpstan-modules` (bloquant) tourne sur `phpstan-modules.neon`.
- [ ] Application layer : enrichir les Actions dans Growth, Platform, Onboarding, Accounting
  (trop peu d'Actions, controllers trop épais)

### Trajectoire PHPStan (niveau actuel → cible)

_Issue #1413, complétant le suivi de la ligne ci-dessus._

| Config | Portée | Niveau actuel | Statut CI | Cible |
|---|---|---|---|---|
| `phpstan.neon` | tout `app/` | `max` | non branché à un job CI dédié (voir `phpstan-modules`/`phpstan-strict` ci-dessous) | n/a |
| `phpstan-modules.neon` | `app/Core`, `app/Modules`, `app/Shared` | `5` | **bloquant** (`architecture-check.yml`, job `phpstan-modules`) | rester à 5 pour l'instant ; réévaluer une montée à 6/7 une fois `phpstan-strict` stabilisé (voir ci-dessous) |
| `phpstan-strict.neon` | `app/Core`, `app/Modules`, `app/Shared` | `8` | **bloquant sur delta uniquement** depuis #1413 (`phpstan-strict-baseline.neon` : 1 667 messages / somme des `count:` = 3 317 au 2026-09-05, en réduction continue ; `continue-on-error` retiré du job `phpstan-strict`) | réduire progressivement la baseline module par module (voir répartition ci-dessous), jamais l'augmenter |

**Répartition de la baseline `phpstan-strict` par module (1 667 messages / Σ 3 317 au 2026-09-05) :**
voir `api/phpstan-strict-baseline.neon` — la baseline est réduite module par module, dans des PR dédiées (chiffres des 2026-08-01/31 périmés : 2950 → 1297 ; régénérée à la hausse depuis par l'ajout des verticales récentes).

Convergence visée : `phpstan-modules` (5) et `phpstan-strict` (8) ne sont pas censés converger vers un seul niveau à court terme — `phpstan-modules` reste le gate rapide et bloquant sur tout PR touchant `app/Core`/`app/Modules`/`app/Shared`, tandis que `phpstan-strict` sert de garde anti-régression progressive sur le typage strict (niveau 8) sans bloquer sur la dette existante. Toute réduction de la baseline (fichier corrigé et retiré de `phpstan-strict-baseline.neon`) doit être faite module par module, dans des PR dédiées, pour rester revuable.

### ✅ Nettoyage complet — bilan cumulé (PR #824 + phase2)

| Supprimé / Migré | Quantité |
|---|---|
| Controllers `app/Http/Controllers/Api/V1/` supprimés | 90 |
| Services `app/Services/` doublons supprimés | 26 |
| Services `app/Services/` shims backward-compat supprimés | 17 (2026-08-11, #1728) |
| Modèles `app/Models/` migrés vers les modules — répertoire **supprimé** (shims retirés) | 92 |
| FormRequests migrés vers modules | 64 |
| `app/Shared/` peuplé (Traits, Attributes, Enums) | ✅ |
| `Core/Tenant/TenantManager` canonique | ✅ |
| `app/DTOs/` supprimé | ✅ |

**`app/Services/`** : **répertoire supprimé (2026-08-11, #1728)** — les 17 derniers shims backward-compat ont été retirés ; tous les consommateurs référencent les canoniques (`App\Core\…` / `App\Modules\…`).
**`app/Http/Requests/`** : répertoire **supprimé** (2026-08-31) — les 64 FormRequests migrés vers les modules, shims backward-compat retirés. Canonical dans les modules.

---

## Commandes utiles

```bash
# Vérifier qu'aucun controller legacy App\Http\Controllers\Api\V1 ne subsiste dans les routes
grep -r "App\\Http\\Controllers\\Api\\V1" api/routes/

# Vérifier qu'aucun service legacy App\Services\* ne subsiste hors app/Services/
grep -rn "App\\Services\\" api/app/ api/tests/ --include="*.php" | grep -v "app/Services/"

# Lancer PHPStan sur Core et Modules (gate bloquant = phpstan-modules.neon, niveau 5)
vendor/bin/phpstan analyse --configuration=phpstan-modules.neon app/Core app/Modules

# Vérifier que tous les ServiceProviders sont enregistrés
cat bootstrap/providers.php

# Vérifier la structure de modules DDD (dynamique — voir aussi
# .github/workflows/architecture-check.yml, généré depuis app/Modules/* sans liste codée en dur)
# NB: seule Absence reste une façade Interfaces/+Providers/ (derogation
# PA2-ARCH-002, voir plus haut). Expense n'a pas de couche Application/ : le
# script signale donc « Missing: Expense/Application » — état acté (module DDD
# partiel depuis #5235, chantier ouvert 2026-09-05)
FACADE_ONLY_MODULES="Absence"
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
