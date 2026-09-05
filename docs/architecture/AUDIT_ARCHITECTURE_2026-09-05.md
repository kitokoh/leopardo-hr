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

## 6. Complément — 2ᵉ passe d'audit fusionnée (constats supplémentaires vérifiés)

> Section ajoutée lors de la **fusion des deux espaces d'audit** (branche unifiée
> `audit/architecture-2026-09-05`, PR #6832 — l'ancienne branche
> `pm/audit-architecture-2026-09-05` et sa PR #6830 ont été fermées). Ces constats
> proviennent de la 2ᵉ passe ; chacun est vérifié (fichier : ligne / commande).

### 6.1 Gardes d'architecture annoncées mais non branchées en CI (priorité haute)

| Constat | Preuve | Recommandation |
|---|---|---|
| **Garde MAT-002** (`dev-hub/tools/check-bounded-context-dependencies.sh`) **absente de tous les workflows** et de `scripts/pre-push-checks.sh` ; exécutée sur main elle **échoue (566 violations, exit 1)** car `dev-hub/governance/bounded-context-dependencies.json` est figé au 2026-08-28 (7 BC absents : 15/16/17/22/24/25/26 ; 4 paires d'allowlist postérieures sans arête) | `grep -r check-bounded-context-dependencies .github/workflows/ scripts/` = 0 ; exécution locale | Rebaseler la matrice sur main puis **brancher MAT-002 en CI** (job du `architecture-check.yml`, même pattern que MAT-001) — tant qu'elle n'est pas branchée, la dette n'est jamais mesurée |
| **Garde CRM** `check-crm-boundary-imports.sh` : l'ADR 0018 affirme qu'elle tourne dans `architecture-check.yml` — **0 occurrence** dans les workflows (module CRM = 196 fichiers, 0 hard block, 0 exemption — garde verte en local mais non câblée) | `docs/architecture/adr/0018-…` vs `grep -i crm .github/workflows/architecture-check.yml` | La brancher dans `architecture-check.yml` (la mention « branchée en CI » a été retirée d'`api/ARCHITECTURE.md` — c'était faux) |
| Zones hors périmètre des gardes #5584/#5745 : `app/Http` (14 paires→Modules), `app/Jobs` (8), `app/Console` (12, dont Console→CRM ×5), `app/Shared` (1) | scans statiques | Étendre la garde d'isolation ou acter ces zones comme adaptateurs |

### 6.2 Gouvernance de `main` — sources contradictoires (docs/outillage, pas de code)

- **Protection de `main`** : `BRANCH_PROTECTION_REQUIRED.md` (racine) déclare `enforce_admins`
  **true** ; `dev-hub/tools/branch-protection-canonical.json` (référentiel de la garde
  #2011, 2026-08-15) dit **false** ; la copie `.github/BRANCH_PROTECTION_REQUIRED.md`
  dit false ; la protection **réelle** (API GitHub 2026-09-05) : `enforce_admins`
  **true**, 5 required checks, merge queue active. → Re-synchroniser canonique + copie
  `.github/` sur le réel (le commentaire CODEOWNERS a été rafraîchi ; le canonique non).
- **`dev-hub/prompts/12_MERGE_ALL_TO_MAIN.md:26,50`** enseigne `gh pr merge --admin`
  pour **bypasser la protection** — contredit `AGENTS.md`, les protocoles CRM/BC et
  l'incident #2011. → Neutraliser ce prompt.
- **`.github/PULL_REQUEST_TEMPLATE.md`** autorise `Closes #N` dans le titre, alors
  qu'`AGENTS.md` (#2512) exige le **body** ; `00_AGENT_QUICK_CARD.md:82-84` enseigne un
  nommage `fix/issue-<N>` et un format de commit invalide `fix/feat:`. → Aligner.
- **`CONVENTIONS.md`** (corrigé dans cette fusion) : liste des modules portée à **25**
  (ordre alphabétique — la liste en contenait encore 18), baseline PHPStan strict
  chiffrée (**1 667 entrées / Σ 3 317** au lieu de « ~2950 »), §4.1 : nommage
  `fix/<issue>-<slug>` / `bc/<code>-<slug>` posé comme **verrou anti-doublon** (#2400).

### 6.3 Chantier restant — constats supplémentaires (issues à ouvrir)

- **[P1]** `api/app/Modules/HR/Application/Actions/ApplySectorTemplate.php` : **fichier
  PHP corrompu** — pas de `<?php`, pas de namespace ni classe (99 lignes de `use` +
  fonctions nues), 0 référence dans le repo, dans un module marqué « complet ».
  → Corriger depuis l'historique ou supprimer (précédent : purge des ~140 fichiers
  corrompus par les merges union, train #6817/#6818).
- **[P1]** `dev-hub/governance/route-owners.json` : **6 fichiers de routes absents**
  dont les plus gros — `travelagency.php` (389 l.), `restaurantmanager.php` (361 l.),
  `fuel_station.php` (252 l.), `edu_manager.php` (173 l.), `delivery.php` (97 l.),
  `solutions.php` (27 l.) ≈ 1 300 lignes (~32 %) sans owner déclaré ; BC-15 FUEL
  déclare `routes: []` alors que `fuel_station.php` existe. → Régénérer + synchroniser.
- **[P2]** `CHANGELOG.md` : 516 Ko (> limite 150 Ko, CONVENTIONS §4.3) et **structure
  corrompue** — des bulletins `[Unreleased]` sont placés **au-dessus** du header
  `## [Unreleased]` (l.1-12) et des notes « ⚠️ En attente de merge » sont collées au
  milieu de bulletins (résidus de merges union). → Re-structurer + archiver.
- **[P2]** Comptages périmés encore présents hors des docs canoniques :
  `docs/architecture/COMPTABILITE_CONCEPTION.md` (« 18 modules »),
  `docs/architecture/adr/0012-…` (« 19 modules, 6 apps », cite `Training` jamais créé),
  `docs/CONTEXT/02_TECHNICAL_CONTEXT.md` & `docs/GOTO_MARKET/01_PRODUCT/PRODUCT_BRIEF.md`
  (« 18 modules ») — réel : 25 modules, 8 packages mobiles.
- **[P2]** Fichier PHP corrompu / fragments morts : 8 commentaires « Migrated from
  App\Http\Controllers\Api\V1… » résiduels (cosmétique).

### 6.4 Corrections apportées par la fusion (2ᵉ passe → branche unifiée)

- `dev-hub/governance/bounded-context-registry.json` : exception partagée
  `api/app/Core/Shared` (**dossier inexistant**) → **`api/app/Shared`** (dossier réel,
  12 fichiers PHP) ; doublon `RestaurantManager` (`planned`) retiré du BC-25.
- `.github/workflows/architecture-check.yml` : commentaire périmé « dette existante
  (16/18 modules, 57 paires) » → renvoi au comptage affiché par la garde.
- `CONVENTIONS.md`, `api/ARCHITECTURE.md`, `ARCHITECTURE.md`, `AGENTS.md`,
  `.github/workflows/README.md` : voir §6.2 + corrections communes des deux passes
  (25 modules, Expense partiel, statuts réels par couche, coverage 65 %, 8 packages
  mobiles). Aucun changement de code applicatif.

— Fin du complément (fusion des espaces d'audit, 2026-09-05).