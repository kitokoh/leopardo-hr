# Audit Architecture — 2026-09-05

> Rapport de l'audit « vérité & conformité » du monolithe DDD (branche `main`, HEAD `357a2b040`).
> Réalisé par 4 PM travaillant sur une branche unique. Toute correction chiffrée a été
> **mesurée sur le disque** (find/grep/wc), jamais déduite d'un autre document.

## 1. Ce qui a été corrigé ce jour (commits sur `main`)

| Commit | Sujet |
|---|---|
| `docs(architecture)` | `ARCHITECTURE.md` : arbre mobile 8 apps (+travel_agent), `marketing/` retiré, 54 pipelines, shims/aliases supprimés, CI réelle (tests.yml backend-only, web-ci E2E, deploy-main=dev/test) |
| `docs(architecture)` | `ARCHITECTURE_STATUS.md` : table régénérée depuis le disque (25 lignes uniques, titre dédoublonné, statuts Expense/Absence exacts) |
| `docs(api)` | `api/ARCHITECTURE.md` : allowlist 23 sources/55 paires, garde CRM active, Expense « partiel » (Application absente), 67 JsonResource, baseline phpstan 1667 entrées/3317 erreurs |
| `docs(api)` | Tableau des modules : fin des faux positifs « ✅ complet » (Payroll, Planning, Recruitment, Fleet, Growth, FuelStation — couches réellement vides) |
| `docs(ci)` | `workflows/README.md`, `ARCHITECTURE_CICD.md`, `DEVELOPMENT.md` : coverage 65 % appliqué, phpstan-strict bloquant sur delta, deploy-main = dev/test, +edge-ci/+pages-deploy, `docs/OPS` → `docs/ops` |
| `docs(mobile)` | Inventaires mobiles réconciliés (8 apps) : `mobile_apps/README.md` (bullet dupliqué corrigé), `CONVENTIONS.md`, `AGENTS.md` |
| `chore(hygiene)` | CODEOWNERS dédupliqué (6 lignes) + commentaire protection rafraîchi ; `leopardo_travel_agent` : `.gitignore` + 25 fichiers `build/` désindexés |

## 2. Chantier restant — dette structurelle mesurée (à traiter en PR dédiées)

> Aucune de ces dettes ne bloque la CI (les gardes vérifient l'existence des dossiers,
> pas leur contenu). Elles sont listées **avec leurs chemins** pour éviter que deux devs
> s'y attaquent en même temps : **1 item = 1 issue = 1 branche** (protocole #2400).

### 2.1 Couches `Application/` vides (cas d'usage à créer) — BC concernés
| Module | État mesuré | Action |
|---|---|---|
| `Expense` | 8 PHP ; `Application/` inexistante ; exemption CI `FACADE_ONLY_MODULES="Absence Expense"` | Créer la couche Application (PA2-ARCH-011 partiel) puis retirer de l'exemption |
| `Fleet` | `Application/` et `Infrastructure/` vides (0 PHP) ; contrats `Domain/Contracts/{Trip,Vehicle}RepositoryInterface` sans implémenteur identifié (grep) | Peupler ou supprimer les contrats morts |
| `FuelStation` | `Application/` vide (0 PHP) malgré 172 PHP ailleurs | Créer les Actions |
| `Planning` | `Application/` vide (`.gitkeep` seul) | Créer les Actions ; Planning reste propriétaire canonique des modèles Absence/Expense |
| `Recruitment` | `Application/` vide (0 Action) | Créer les Actions |
| `Payroll` | `Application/` = 1 Service, 0 Action (module de 133 PHP) | Créer les Actions (`Domain/Services/PayrollLineLabels.php` à déplacer ?) |

### 2.2 Placement / conventions
- `Cameras` : 4 modèles à `Domain/` racine (namespace `…\Domain`, PSR-4 **correct** —
  déviation cosmétique du template `Domain/Models/`). Décision : aligner ou acter.
- Controllers posés directement sous `Interfaces/Api/V1/` au lieu de `.../Controllers/` :
  `Cabinet/.../CabinetDocumentController.php` (+ Requests), `Attendance/.../KioskEnrollmentController.php` (mineur).
- `Restaurant` : `Interfaces/` vide par conception — les routes publiques `solutions.php` sont servies par
  `Core\Solutions` (commentaire du `RestaurantServiceProvider`) ; le module n'expose que Domain + Provider.

### 2.3 Frontières de BC à trancher (décision d'architecture requise)
- Agrégat `Employee` canonique dans `Core/Auth` (importe les Domain de 5 modules) —
  chantier #5584 **non clos** malgré des docs le présentant comme tel.
- `app/Shared/` viole « Shared ne dépend de rien » : `Traits/Approvable.php` → `Modules\Attendance`,
  `BelongsToCompany` → `Core/Tenant`, `Auditable`/`EmployeeNotifier` → `Core/Auth`.
- Interfaces dupliquées : `SolutionManifest` ×4 (Core/Solutions, Delivery, RestaurantManager,
  TravelAgency) ; `PaymentGatewayInterface` ×3 signatures divergentes (Accounting,
  RestaurantManager, TravelAgency).
- Couplage bidirectionnel Payroll⇄Billing en Domain (`Payroll/Domain/Models/{Commission,Payment}`
  ↔ `Billing/Domain/Models/{Invoice,Partner}`).

### 2.4 Zones legacy / non documentées
- `app/Policies/` (41 classes, 4 conventions) et `app/Contracts/` (6 fichiers, 53
  consommateurs) absents des docs d'architecture → les documenter ou les migrer.
- `app/AI` importe des Domain de modules hors périmètre de la garde d'isolation ;
  `app/Core/AI` absent de toute doc.
- Routes intra-modules chargées par providers (`Attendance/routes/geo.php`, `HR/routes/candidate_hiring.php`,
  `EdgeSync/routes/api.php`) — hors tableau des routes `routes/modules/`.

### 2.5 Tests par module (colonne Tests d'ARCHITECTURE_STATUS.md)
⚠️ = aucun dossier de tests dédié : `Fleet`, `Planning`, `Recruitment`, `RestaurantManager`.
(`Feature/Restaurant` ne référence pas `RestaurantManager`.)

## 3. Coordination multi-dev / multi-PM — règles actuelles et manques

### Déjà en place (solide)
- Registre machine-readable **26 BC** : `dev-hub/governance/bounded-context-registry.json`
  (+ CODEOWNERS aligné, garde `check-bounded-context-registry.sh`, MAT-001/#5859).
- Protocole branche = lock + claim marker (#2400), « 1 agent par BC », protocole lot
  `bc/<code>-<slug>`, gardes `branch-hygiene` / `pr-issue-guard` (1 issue = 1 PR).

### Manques (propositions — décision requise)
| # | Manque | Proposition |
|---|---|---|
| M1 | Le registre BC ne couvre que `api/` — `front/`, `edge/`, `dev-hub/`, `shared/i18n/`, `leopardo_core` **sans propriétaire** | Étendre le registre avec des BC/surfaces « shared_surfaces » (ex. BC-00 core partagé) + garde CI |
| M2 | CODEOWNERS documentaire uniquement (0 review requise) | Activer `require_code_owner_reviews` dès qu'un 2ᵉ mainteneur rejoint (déjà noté dans CODEOWNERS) |
| M3 | Pas de règle « surface partagée = écrivain unique à la fois » | Label `surface-shared` + annonce d'intention sur l'issue avant modification de `leopardo_core`/`shared/i18n` |
| M4 | Commits sans scope BC ni n° d'issue | Convention : `feat(BC-07):` + numéro d'issue dans le nom de branche (étendre au-delà de CRM) |
| M5 | 5 inventaires mobiles divergents (corrigés ce jour) | `melos.yaml` reste la source canonique ; les docs pointent vers lui |

## 4. Gardes locales exécutées (2026-09-05)
- `dev-hub/tools/check-architecture-docs-parity.sh` → OK (25 modules ×4 docs, 0 fantôme).
- `dev-hub/tools/check-bounded-context-registry.sh` → OK (26 BCs).
- Vérifs ponctuelles : modules legacy absents, 0 import `Api\V1` legacy, routes OK.

## 5. Non vérifié (pas d'outillage local)
PHP/Composer/Flutter absents du sandbox : les jobs CI (PHPUnit, PHPStan, Pint, builds)
n'ont pas été rejoués localement — ils tourneront sur la branche à la prochaine PR/push.
