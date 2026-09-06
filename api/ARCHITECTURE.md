# Architecture — leopardo-hr API
> Document de référence maintenu à jour. Toute déviation doit être discutée et documentée ici.

---

## Vision

L'API `leopardo-hr` suit un modèle **Domain-Driven Design (DDD) modulaire** :

- **`app/Core/`** — Socle transversal SaaS (Auth, Tenant)
- **`app/Modules/`** — Domaines métier autonomes (HR, Payroll, Attendance, etc.)
- **`app/Shared/`** — Kernel partagé entre modules (DTOs, Enums, Events, Exceptions, Traits) — **ne dépend que de `Core/`, jamais de `Modules/`** (règle précisée 2026-09-06, #6844 : les traits/contrats transverses peuvent référencer Core/Tenant, Core/Auth… ; toute dépendance vers un module = le code doit vivre dans ce module ou être supprimé)
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

**Zones transverses réelles (complément 2026-09-05, audit PM)** — où vit le code qui
n'est ni module, ni Core, ni Shared :

| Zone | Rôle | Propriétaire / BC |
|---|---|---|
| `app/Http/Controllers/Web/` | Surface session/blade (kiosque, dashboard, invitations) — dérogation actée (audit #6578) | transverse (hors API DDD) |
| `app/Http/Resources/Api/V1/` | **67 JsonResource centralisées** (dérogation PA2-ARCH-010 — partage inter-modules) | transverse |
| `app/Http/Middleware/` | Middlewares HTTP (tenant, RBAC, structured logging…) | transverse |
| `app/Jobs/` | Jobs/queues transverses (drain, purge…) — les jobs métier vivent dans les modules | BC-14 INTEGRATION (registre) |
| `app/Console/` | Commandes artisan (72 fichiers) — trier : les commandes métier doivent migrer vers les modules | registre BC (par domaine) |
| `app/Policies/` | Policies RBAC partagées (41 classes) — les policies de module vivent dans le module | BC-03 IDENTITY (registre) |
| `app/Contracts/` | Contrats transverses (Queue, Communication, Feature) | BC-13/BC-14 (registre) |
| `app/Events/`, `app/Listeners/` | Events/écouteurs transverses — catalogue `dev-hub/governance/event-catalogue.json` (MAT-006) | par BC (registre) |
| `app/Exceptions/`, `app/Mail/`, `app/Notifications/` | Infrastructure applicative partagée | transverse |
| `app/Shared/Models/` | Modèles réellement partagés (ex. `Language`) | exception partagée (registre) |
| `app/AI/` | Module IA autonome (orienté agent, non DDD classique) | BC-23 AI (registre) |

> Règle : **tout nouveau code métier va dans `app/Modules/<BC>/`** ; les zones
> ci-dessus ne reçoivent que de l'infrastructure réellement partagée, avec un
> propriétaire déclaré au registre BC (MAT-001).

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

La dette héritée (23 dossiers sources — 20 modules + `Core/Auth`, `Core/Feature`,
`Core/Tenant` —, 55 paires source→cible dont 7 `Core→Modules` — vérifié
2026-09-05) est **actée** dans
`dev-hub/tools/module-isolation-allowlist.txt` et doit **diminuer** : toute
ligne ajoutée exige une justification (issue de refactor), aucune n'est
rajoutée « pour passer ». Dérogations structurelles déjà documentées :
`Modules/Absence` est une façade sur `Planning` ; `Modules/Expense` a évolué en module DDD partiel depuis #5235 (voir dérogation PA2-ARCH-011, à jour plus bas)
(PA2-ARCH-002 / PA2-ARCH-011). Refactors de résorption en cours : #5591
(PayrollCalculator → extraire `Planning`) ; déplacement de l'`Employee`
canonique hors de `Core/Auth` (#5584 item 3, chantier à part — 361
consommateurs).

**Frontières du module CRM client (issue #5745, ADR 0018) :** le module
`CRM` (CRM client tenant, cf. ADR-CRM-DUAL-CONTEXTS) est couvert par une garde
orientée dédiée, `dev-hub/tools/check-crm-boundary-imports.sh`, en complément
de la garde symétrique #5584 ci-dessus. Elle interdit en HARD BLOCK tout
`use App\Modules\{Platform,Marketing,Payroll,Accounting}\` dans
`api/app/Modules/CRM/**` (CRM commercial, paie, comptabilité — aucun échange
direct), et exige une exemption justifiée dans
`dev-hub/tools/crm-boundary-allowlist.txt` pour tout autre import
inter-module. Les imports `App\Core\*`, `App\Shared\*` et intra-module
restent libres. `Modules/CRM` existe et est complet (`CrmServiceProvider`) ; la garde `check-crm-boundary-imports.sh` est **active et verte en local** (0 hard block, 0 exemption consommée) mais **n'est pas encore branchée en CI** (constat 2026-09-05 — branchement à faire dans `architecture-check.yml`, cf. rapport d'audit).

| Module | Statut routes | Statut code | ServiceProvider |
|--------|--------------|-------------|-----------------|
| `Core/Auth` | ✅ routes/api.php | ✅ complet | — (AppServiceProvider) |
| `Core/Tenant` | — | ✅ migré (TenantManager canonique) | — |
| `Modules/HR` | ✅ routes/modules/rh.php + hr_extended.php | ✅ complet | `HRServiceProvider` |
| `Modules/Payroll` | ✅ routes/modules/payroll_engine.php | 🔶 Application en construction (ADR-0020, #6896) : 1 Service (régularisation) + 0 Action — extraction par lots cartographiée (`PAYROLL_APPLICATION_CARTOGRAPHIE.md`, lot 1 = cycle de paie) ; Domain/Infrastructure/Interfaces complets | `PayrollServiceProvider` |
| `Modules/Attendance` | ✅ routes/modules/rh.php | ✅ complet | `AttendanceServiceProvider` |
| `Modules/Planning` | ✅ routes/modules/planning.php | ✅ Application peuplée (Actions cycle de vie absence — Create/Update/Approve/Reject/Cancel, 2026-09-06 #6895) ; reste propriétaire canonique des modèles Absence/Expense | `PlanningServiceProvider` |
| `Modules/Absence` | ✅ routes/modules/absence.php | 🔶 Interfaces + Providers uniquement (derogation documentee, PA2-ARCH-002) | `AbsenceServiceProvider` |
| `Modules/Expense` | ✅ routes/modules/expense.php | ✅ DDD complet depuis 2026-09-06 (#6894) : Domain/Infrastructure/Interfaces/Providers (écritures comptables #5235) + **Application/Actions** (`GenerateExpenseAccountingEntries`, `VoidExpenseAccountingEntries`) — exemption CI levée (ne couvre plus qu'`Absence`) ; modèles de notes de frais sous contrat `Planning` | `ExpenseServiceProvider` |
| `Modules/Notification` | ✅ routes/api.php + dashboard.php + hr_extended.php | ✅ complet | `NotificationServiceProvider` |
| `Modules/Recruitment` | ✅ routes/modules/hr_extended.php | 🔶 Application vide (0 Action) — Domain/Infrastructure/Interfaces présents | `RecruitmentServiceProvider` |
| `Modules/EduManager` | ✅ routes/modules/edu_manager.php | 🟢 verticale BC-16 (EDU-001..020, core + batch2 + batch3) | `EduManagerServiceProvider` |
| `Modules/RestaurantManager` | ✅ routes/modules/restaurantmanager.php | 🟢 verticale BC-25 (Application/Domain/Infrastructure/Interfaces/Providers) | `RestaurantManagerServiceProvider` |
| `Modules/Restaurant` | ✅ routes/modules/solutions.php (public) | 🔶 **Fournisseur de contenu** (Solution/Survey) — Application/Infrastructure/Interfaces **N/A intentionnel** (ADR-0020, #6901) ; webhooks/shop via RestaurantManager, surveys via `Core\Solutions` | `RestaurantServiceProvider` |
| `Modules/Billing` | ✅ routes/modules/billing.php | ✅ complet | `BillingServiceProvider` |
| `Modules/Cabinet` | ✅ routes/modules/cabinet.php | ✅ complet | `CabinetServiceProvider` |
<<<<<<< HEAD
| `Modules/Fleet` | ✅ routes/modules/hr_extended.php | 🔶 Application et Infrastructure vides (0 PHP) — Domain + Interfaces seuls | `FleetServiceProvider` |
| `Modules/Catalog` | 🔶 socle domaine BC-28 — routes API privée à venir (C-API #6881) | 🟢 socle domaine BC-28 (#6880) : migrations tenant `catalog_categories`/`catalog_products`, modèles, policies deny-by-default, feature flag `b2b_catalog` | `CatalogServiceProvider` |
=======
| `Modules/Fleet` | ✅ routes/modules/hr_extended.php | 🔶 Application et Infrastructure **vides actées** (ADR-0020, #6899) — conservées, à peupler au fil des besoins fonctionnels ; Domain + Interfaces seuls aujourd'hui | `FleetServiceProvider` |
>>>>>>> 3b0052432 (docs(architecture): état Fleet/Restaurant acté (N/A fournisseur de contenu, vides au fil des besoins) — ADR-0020 (#6899 #6901))
| `Modules/Cameras` | ✅ routes/modules/cameras.php | ✅ complet | `CamerasServiceProvider` |
| `Modules/CRM` | ✅ routes/modules/crm.php | ✅ complet (CRM client, ADR-CRM-DUAL-CONTEXTS) | `CrmServiceProvider` |
| `Modules/FuelStation` | ✅ routes/modules/fuel_station.php | 🔶 Application vide (0 PHP) — Domain/Infrastructure/Interfaces/Providers complets | `FuelStationServiceProvider` |
| `Modules/Delivery` | ✅ routes/modules/delivery.php | 🟢 verticale BC-26 consolidée (#6757, PHPStan assaini #6759) | `DeliveryServiceProvider` |
| `Modules/EdgeSync` | ✅ module routes | ✅ complet | `EdgeSyncServiceProvider` |
| `Modules/TravelAgency` | ✅ routes partagées + publiques shop | 🟢 fondations verticale BC-24 (TRAVEL-101..108, 201..203 + shop/e-billets) | `TravelAgencyServiceProvider` |
| `Modules/Growth` | ✅ routes/modules/growth.php | 🔶 Infrastructure vide (0 PHP) — Application/Domain/Interfaces présents | `GrowthServiceProvider` |
| `Modules/Marketing` | ✅ routes/modules/marketing.php | ✅ complet | `MarketingServiceProvider` |
| `Modules/Onboarding` | ✅ routes/api.php | ✅ complet | `OnboardingServiceProvider` |
| `Modules/Platform` | ✅ routes/api.php | ✅ complet | `PlatformServiceProvider` |
| `Modules/Accounting` | ✅ routes/modules/accounting.php | ✅ complet | `AccountingServiceProvider` |

> **Derogation documentee — API Resources centralisees (PA2-ARCH-010)** : contrairement au schema DDD par module (`Modules/<Nom>/Interfaces/Api/V1/Resources/`) documente en §2.7 de `CONVENTIONS.md` et repris par le stub `stubs/module-template/`, les **67 classes `JsonResource`** de l'API (vérifié 2026-09-05) vivent toutes dans `app/Http/Resources/Api/V1/` (namespace legacy centralise) et sont importees a la piece par ~50 controllers de modules differents. C'est un choix delibere, pas une migration inachevee : plusieurs Resources sont **partagees entre modules** (ex. `LoanResource` par `HR` et `Payroll`, `AttendanceTodayResource` par `Attendance` et `HR`, `PayrollRunResource`, `VehicleAlertResource`/`VehicleMaintenanceResource`/`VehicleTripResource` par `Fleet` depuis plusieurs controllers, `InterviewResource`/`JobPostingResource`/`ApplicantResource` par `Recruitment` depuis plusieurs controllers). Deplacer chaque Resource dans le module qui l'a cree obligerait les autres modules consommateurs a l'importer inter-module — ce qui viole l'interdiction stricte ci-dessus (« un module n'importe jamais directement les classes d'un autre module »). Garder les Resources centralisees dans `app/Http/Resources/Api/V1/` evite ce couplage inter-module et reste coherent avec `App\Shared\*` (namespace commun consomme par tout le monde, ne dependant de rien). Le template `stubs/module-template/Interfaces/Api/V1/Resources/` et `docs/architecture/module-creation-guide.md` ont ete corriges pour ne plus induire les nouveaux modules en erreur (voir PA2-ARCH-010) : les Resources d'un nouveau module vont dans `app/Http/Resources/Api/V1/` sauf si elles sont strictement internes et jamais partagees, auquel cas elles peuvent rester dans `Interfaces/Api/V1/Resources/` du module.

**Légende :** ✅ complet | 🟢 verticale consolidée/complétée via le merge 2026-09-02 | 🔶 partiel / en cours | 🔄 migration partielle en cours

> **Derogation documentee — `Modules/Absence` (PA2-ARCH-002)** : ce module ne possede que `Interfaces/` (controllers `AbsenceController`/`LeavePolicyController` + Requests) et `Providers/`. Les couches `Domain/Application/Infrastructure` ont ete supprimees car elles dupliquaient integralement (memes colonnes, memes tables) les modeles/services reels du module `Planning` (`Planning\Domain\Models\{Absence,AbsenceType,LeaveBalance,LeaveBalanceLog}`, `Planning\Infrastructure\Services\AbsenceService`) : ceux-ci sont deja references par 100% des tests, controllers, events, resources, policies et seeders de conges/absences. `Planning` est desormais le seul proprietaire canonique des modeles d'absence ; `Modules/Absence` reste uniquement une facade HTTP (routes + controllers) qui consomme les classes `Planning\...` directement, en attendant une eventuelle extraction complete du domaine Absence hors de Planning.
>
> **Historique — `Modules/Expense` (PA2-ARCH-011, issue #1414, évolution #5235)** : la dérogation initiale (2024-2026) documentait un module « façade HTTP » ne possédant que `Interfaces/` (`ExpenseClaimController`) et `Providers/`, les modèles métier (`ExpenseClaim`, `ExpenseItem`) vivant canoniquement dans `Planning\Domain\Models` (le controller routé consommait `Planning\...\ExpenseClaim` directement ; la policy enregistrée sur le modèle mort avait été corrigée).
> **État actuel (depuis #5235, complété #6894 le 2026-09-06)** : `Modules/Expense` est un module DDD **complet** — `Domain/Models/ExpenseAccountingEntry`, `Domain/Exceptions/UnbalancedExpenseEntriesException`, `Application/Actions/{GenerateExpenseAccountingEntries,VoidExpenseAccountingEntries}` (cas d'usage nommables, consommés par le contrôleur comptable et l'observer), `Infrastructure/{Listeners,Services}` (persistance/écritures, flux Expense → Accounting) et `Interfaces/Api/V1/Controllers/ExpenseAccountingController` (routes `/expense-claims/{id}/accounting-entries` actives). La dérogation PA2-ARCH-011 ne s'applique plus : **Expense est sorti de l'exemption CI** (`FACADE_ONLY_MODULES="Absence"` — seul `Absence`, façade pure PA2-ARCH-002, reste exempté). Seuls les modèles de *notes de frais* restent sous contrat `Planning` (propriétaire canonique historique).

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
| `phpstan-strict.neon` | `app/Core`, `app/Modules`, `app/Shared` | `8` | **bloquant sur delta uniquement** depuis #1413 (`phpstan-strict-baseline.neon`, 1667 entrées / 3317 erreurs gelées au 2026-09-05, en réduction continue ; `continue-on-error` retiré du job `phpstan-strict`) | réduire progressivement la baseline module par module (voir répartition ci-dessous), jamais l'augmenter |

**Baseline `phpstan-strict` (1667 entrées / 3317 erreurs, vérifié 2026-09-05) :**
le fichier `api/phpstan-strict-baseline.neon` fait foi — la baseline est réduite module par module, dans des PR dédiées.

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
# NB: seul Absence (façade pure Interfaces/+Providers/, PA2-ARCH-002) est
# exempté de l'exigence Application/Domain/Infrastructure. Expense a rejoint
# le DDD complet le 2026-09-06 (#6894 — couche Application/Actions créée) :
# la dérogation PA2-ARCH-011 (modèles de notes de frais canoniques dans
# Planning) est historique, le module n'est plus dans FACADE_ONLY_MODULES.
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
