# Audit Architecture — Rapport du PM Architecture (2026-09-05)

> **Rôle** : PM Architecture / assurance qualité architecturale.
> **Branche unique de travail des 4 PM** : `pm/audit-architecture-2026-09-05` (créée le
> 2026-09-05 — TOUS les PM committent sur CETTE branche ; la PR d'intégration finale
> vers `main` sera ouverte depuis elle).
> **Périmètre de ce rapport** : véracité docs vs dépôt, structure DDD (fichiers/dossiers
> à leur place), frontières des bounded contexts, règles de collaboration dev,
> « chantier architectural » — constats vérifiés, corrections livrées, chantier restant.
> **Méthode** : audits croisés (scouts read-only) + recoupement manuel des assertions
> sur le code (`ls`, `find`, `grep`, gardes CI exécutées localement). Rien n'est écrit
> ici sans preuve. Commit de référence audité : `357a2b040` (main, 2026-09-05).

---

## 1. Ce qui est VRAI sur main (vérifié — à ne pas « corriger »)

- **25 modules DDD** sous `api/app/Modules/` (liste exacte : voir `api/ARCHITECTURE.md`
  table). **8 dossiers legacy supprimés** : `app/Models`, `app/Services`,
  `app/Http/Controllers/Api/V1`, `app/Http/Requests`, `app/Traits`, `app/Attributes`,
  `app/Enums`, `app/DTOs` — 0 référence résiduelle (`grep` = 0).
- `app/Shared/` contient tout ce qui est documenté (PaginationDTO, BelongsToCompany,
  Auditable, Approvable, ApiFeature, RequiresPermission, MobileCompatible, ApiError,
  DomainException) + 3 extras documentés nulle part (Contracts/Notification/
  EmployeeNotifier, Services/InboundWebhookVerifier, Models/Language).
- **Garde d'isolation #5584 verte et synchronisée** : `check-module-isolation.sh`
  (exit 0), allowlist = exactement le code (0 ligne morte, 0 trou) — 55 paires de dette
  actée. Garde CRM (`check-crm-boundary-imports.sh`) **active** (module CRM existe :
  196 fichiers) et verte, 0 exemption consommée.
- **Registre BC** (`dev-hub/governance/bounded-context-registry.json`) : 26 BC continus
  (BC-01…BC-26), 100 % des 25 modules + `app/AI` mappés, garde MAT-001 verte
  (chemins/routes/migrations/CODEOWNERS cohérents).
- **Gates CI** : Module Structure Validator (5 couches racines × 25 modules, exemptions
  documentées Absence/Expense), PHPStan modules niveau 5 (bloquant) + strict niveau 8
  (gate delta, baselines à ne jamais élargir), coverage 65 % global (required) + 80 %
  Payroll bloquant (`payroll-ci.yml`), TruffleHog, actionlint. Tous câblés et présents.
- **Routes** : chaque module a ses routes (fichiers `routes/modules/*.php` vérifiés
  un par un) ; 0 import legacy. 54 workflows CI/CD (pas 40 — doc corrigée).
- Apps mobiles : 8 dossiers sous `front/mobile_apps/` (7 apps + package `leopardo_core`)
  tous dans `melos.yaml` et dans la matrice `mobile-apps-ci.yml`.

## 2. Constats corrigés dans cette passe (commits A→E, docs/gouvernance uniquement)

| # | Constat (faux positif / ligne fausse) | Correctif livré |
|---|---------------------------------------|-----------------|
| 1 | CONVENTIONS.md listait **18 modules** actifs (7 manquants : CRM, Delivery, EduManager, FuelStation, Restaurant, RestaurantManager, TravelAgency) | Liste = 25 réels + renvoi table `api/ARCHITECTURE.md` |
| 2 | CONVENTIONS.md : section « Verbes HTTP » **dupliquée** en fin de fichier ; numérotation désordonnée (§2.9 avant §2.8) ; caractère `D` orphelin sous §2.6 ; chemin i18n `resources/lang/` inexistant (réel : `api/lang/`) | Dédup, renumérotation §2.8/2.9/2.10, `D` retiré, chemin corrigé |
| 3 | ARCHITECTURE.md racine : « 7 applications mobiles » sans `leopardo_travel_agent` ; `marketing/` listé mais **supprimé** du dépôt ; « 40 pipelines » (réel : 54) ; liste des 25 modules non alphabétique ; état shims « 2026-07-19 » périmé (les 8 dossiers sont supprimés, 0 référence) ; mention d'un alias `App\Services\TenantManager` qui n'existe plus | Arbre = réalité (8 apps, `site/`, 54 workflows, dossiers cachés notés), état shims réécrit et daté |
| 4 | api/ARCHITECTURE.md : ligne `Modules/Restaurant` = « routes solutions.php (public) » faux (les webhooks/shop sont inline `api.php` via RestaurantManager ; surveys via `Core\Solutions`) ; « 60 classes JsonResource » (réel : 67) | Ligne corrigée + note de complétude (validateur CI ≠ sous-couches peuplées) |
| 5 | docs/ARCHITECTURE_STATUS.md : tableau **corrompu par merges union** (CRM ×2, Accounting ×3, RestaurantManager ×3, Delivery/EduManager ×2, Restaurant ×3, en-tête dupliqué, notes « 18 réels » vs « 25 ») | Table reconstruite (25 lignes uniques) depuis le filesystem, méthode reproductible, notes par module, métriques 2026-09-05 |
| 6 | Registre BC : `EdgeSync` en **double propriété** (BC-14 + BC-19) ; doublon `RestaurantManager planned` dans BC-25 ; `api/app/Core/AI` (16 fichiers, ns `App\Core\AI`) **non déclaré** ; 4 dossiers Core transversaux non couverts | EdgeSync → BC-19 DEVICE seul ; doublon supprimé ; Core/AI → BC-23 (actif) + CODEOWNERS ; exceptions documentées pour Core/{Solutions,Notifications,Privacy,Seed}. Garde MAT-001 verte |
| 7 | front/mobile_apps/README.md : puce `leopardo_accounting` **dupliquée** décrivant en réalité l'app marketing ; liste « CI valide » incomplète | Puce → `leopardo_marketing/` ; liste CI = 8 packages réels |
| 8 | README.md racine : lien mort `docs/specifications/PROGRAMME-CRM-INTERNE-CLIENT-COMPLET.md` | Lien → `MODULE_CRM_INTERNE_CLIENT.md` (existe) |
| 9 | docs/architecture/module-creation-guide.md + stub : couche canonique `Domain/Contracts` absente du squelette (alors que l'exemple de SP du guide bind une interface `Domain\Contracts`) | `Domain/Contracts/` ajouté au guide + au template |
| 10 | `.github/workflows/README.md` : `edge-ci.yml` et `pages-deploy.yml` non documentés (52/54) | 54/54 documentés |

**Aucun changement de code applicatif.** Toutes les assertions « supprimé » / « existe »
ont été vérifiées par `ls`/`find`/`grep` avant correction.

## 3. Chantier architectural restant (recommandations priorisées)

> Pour éviter le double travail : **avant de prendre un item, s'assigner l'issue
> correspondante et créer la branche `fix/<issue>-<slug>`** (protocole #2400). Si
> l'item n'a pas d'issue, en créer une avec le label BC + `Agent-Ready`.

- **[P1] Couches `Application/` (Actions/DTOs) à étoffer** — modules sans
  `Application/Actions`+`DTOs` peuplés : Delivery, EduManager, Expense, Fleet,
  FuelStation, Payroll, Planning, Recruitment, RestaurantManager, TravelAgency
  (+ Billing : DTOs vides ; Growth : `Infrastructure/Services` vide ; Onboarding :
  pas de `Domain/Models`). Pattern existant : #6569 (Platform), #5591 (Payroll).
- **[P1] Dette d'isolation depuis les couches `Domain/`** (12 paires allowlistées
  #5584 : des modèles de domaine importent les modèles d'un autre BC — ex.
  `Core/Auth` `Employee` → HR×6, `Payroll` → `Billing`, `Billing` → `Payroll`,
  `Recruitment` → HR). Résorber module par module (contrats) — garde CI déjà en place.
- **[P2] Cameras** : déplacer les 4 modèles Eloquent de `Domain/` racine vers
  `Domain/Models/` (alignement structure canonique).
- **[P2] Doublon de classe** : `api/app/Core/Solutions/Interfaces/Api/V1/
  PlatformSolutionSurveyStatsController.php` n'est pas routé (la version routée est
  `Modules\Platform\Interfaces\...\PlatformSolutionSurveyStatsController`) — supprimer
  le doublon après vérification des usages (`grep -r "Core\\\\Solutions\\\\...Stats"`).
- **[P2] Tests Feature Growth** : 0 fichier de test référençant `App\Modules\Growth`
  (Platform : 8, Onboarding : 2 — faible).
- **[P2] Registre BC** : chemins `planned` fantômes conservés volontairement
  (`api/app/Solutions/*`, `Modules/Documents`, `Modules/Reporting`) — décision
  produit requise : soit retirer, soit matérialiser (ne pas créer les dossiers sans spec).
- **[P3] PHPStan** : les chiffres de baseline cités dans les docs (ex. « 1297 erreurs »)
  doivent être recalculés par une exécution réelle (`phpstan-strict-baseline.neon`
  fait 10 004 lignes — le nombre d'erreurs n'est pas la taille du fichier).
- **[P4] Reste roadmap produit** : Event Sourcing Absence/Expense, RLS PostgreSQL,
  routes/web.php (hors scope API DDD).

## 4. Coordination entre les 4 PM — règles sur la branche unique

1. **Une seule branche** : `pm/audit-architecture-2026-09-05`. `git fetch` avant chaque
   commit, `pull --rebase` avant chaque push. Ne jamais forcer le push.
2. **Un commit = une raison** ; message `type(scope): description` (types repo).
   Toujours une entrée `CHANGELOG.md` sous `## [Unreleased]` pour tout changement de
   comportement, doc, CI ou procédure (règle §4.2 CONVENTIONS).
3. **Éviter les zones des autres** (cette passe PM Architecture n'a touché QUE des
   docs + registre BC + CODEOWNERS — liste exacte des fichiers dans les commits
   A→E). Zones revendiquées par ce rapport : `ARCHITECTURE.md` (racine),
   `api/ARCHITECTURE.md`, `docs/ARCHITECTURE_STATUS.md`, `CONVENTIONS.md`, `AGENTS.md`
   (§ apps mobiles), `front/mobile_apps/README.md`, `dev-hub/governance/
   bounded-context-registry.json`, `CODEOWNERS`, `docs/architecture/
   module-creation-guide.md`, `api/stubs/module-template/`, `.github/workflows/README.md`.
   → Pour toute autre zone, checker `git log` récent avant d'écrire.
4. **Ne pas re-supprimer/créer de module** sans spec `docs/specifications/` validée
   (constitution §I et règle d'or AGENTS.md).
5. **Issues** : tout nouveau chantier = issue avec label BC ; auto-assignation avant
   de commencer ; `Closes #N` dans le corps de la PR finale.
6. La **PR finale unique** vers `main` sera ouverte depuis cette branche (checks
   requis : Backend Coverage, PHPStan Strict L8, Module Structure Validator, ESLint+TS,
   actionlint). Ne pas merger tant que les checks ne sont pas verts.

## 5. Fichiers exacts modifiés par cette passe (pour revue)

`CONVENTIONS.md`, `AGENTS.md`, `ARCHITECTURE.md`, `api/ARCHITECTURE.md`,
`docs/ARCHITECTURE_STATUS.md`, `README.md`, `front/mobile_apps/README.md`,
`docs/architecture/module-creation-guide.md`, `api/stubs/module-template/Domain/Contracts/.gitkeep`,
`.github/workflows/README.md`, `dev-hub/governance/bounded-context-registry.json`,
`CODEOWNERS`, `CHANGELOG.md`.

## 6. Complément d'audit — 2ᵉ passe (constats vérifiés supplémentaires, 2026-09-05)

> Passe complémentaire du même audit, **fichiers : ligne** à l'appui. Rien de
> ce qui suit n'était couvert par les §1-3. Aucun changement de code applicatif.

### 6.1 Gardes d'architecture annoncées mais NON branchées en CI (à corriger en priorité)

| Constat | Preuve | Recommandation |
|---|---|---|
| **Garde MAT-002** (`check-bounded-context-dependencies.sh`) **jamais branchée** en CI ni dans `scripts/pre-push-checks.sh` ; exécutée sur main elle échoue (**566 violations**, exit 1) car `bounded-context-dependencies.json` est figé au 2026-08-28 (7 BC absents : 15/16/17/22/24/25/26 ; 4 paires d'allowlist postérieures sans arête) | `dev-hub/governance/bounded-context-dependencies.json` (`_meta`), `grep` workflows | Rebaseler la matrice sur main, puis **brancher MAT-002 en CI** — tant qu'elle n'est pas branchée, la dette n'est jamais mesurée |
| **Garde CRM** `check-crm-boundary-imports.sh` : l'ADR 0018 affirme qu'elle tourne dans `architecture-check.yml` — **0 occurrence** dans les workflows ; elle est verte en local (module CRM = 196 fichiers, 0 hard block, 0 exemption) mais **personne ne la fait tourner en CI** | `docs/architecture/adr/0018-…:66` | Brancher dans `architecture-check.yml` (l'ADR le promet déjà) |
| **Zones hors périmètre** des gardes #5584/#5745 : `app/Http` (14 paires→Modules), `app/Jobs` (8), `app/Console` (12, dont Console→CRM ×5), `app/Shared` (1) | scans statiques | Étendre la garde d'isolation ou acter ces zones comme adaptateurs |

### 6.2 Gouvernance de `main` — sources contradictoires (aucune modif code)

- `BRANCH_PROTECTION_REQUIRED.md:12` (racine) déclare `enforce_admins` **true** ;
  `dev-hub/tools/branch-protection-canonical.json` (référentiel de la garde #2011,
  commit 2026-08-15) dit **false** ; la copie `.github/BRANCH_PROTECTION_REQUIRED.md`
  dit false ; la protection **réelle** (API GitHub 2026-09-05) : `enforce_admins`
  **true**, 5 required checks, merge queue active. → Re-synchroniser canonique +
  copies sur le réel.
- `dev-hub/prompts/12_MERGE_ALL_TO_MAIN.md:26,50` enseigne `gh pr merge --admin`
  pour **bypasser la protection** — contredit `AGENTS.md`, `CRM_BRANCH_PROTOCOL.md`
  et l'incident #2011. → Neutraliser ce prompt.
- `CONVENTIONS.md` §2.1 déclarait les jobs `phpstan-modules`/`phpstan.neon max`
  « bloquants » : seul « PHPStan — Strict » est un required check (corrigé cette
  passe). `PILOTAGE.md` (archivé) se contredit en interne sur le seuil de
  coverage (65 % l.142 vs 60 % l.226) ; `.github/workflows/README.md` disait
  « 60 % » (corrigé cette passe : 65 %).
- `.github/PULL_REQUEST_TEMPLATE.md` autorise `Closes #N` dans le titre, alors
  qu'`AGENTS.md` (#2512) exige le **body** ; `00_AGENT_QUICK_CARD.md` enseigne un
  format de commit invalide (`fix/feat:`) et un nommage `fix/issue-<N>` hors
  protocole. → Aligner template + QUICK CARD sur AGENTS.md.

### 6.3 Textes faux/périmés corrigés dans cette 2ᵉ passe

- `CONVENTIONS.md` : liste « Modules actifs : (18 noms) » → **liste complète des
  25** (le §2.3 disait déjà 25) ; « ~2950 erreurs » PHPStan → **1 667 messages /
  Σ 3 317 (mesuré 2026-09-05)** ; §4.1 : nommage `fix/<issue>-<slug>` posé comme
  **verrou anti-doublon** (#2400) ; chemin du template DDD → `api/stubs/module-template/`.
- `api/ARCHITECTURE.md` : dette d'isolation « 19 sources / 51 paires » →
  **20 sources / 55 paires** (sortie garde 2026-09-05, +4 justifiées #6816) ;
  baseline PHPStan strict « 1297 » → **1 667 / Σ 3 317** ; module `Expense`
  requalifié « 🟡 partiel » (**couche `Application/` absente**) ; note de
  complétude précisée (RestaurantManager 22 Actions / TravelAgency 35 Actions,
  sans `Application/DTOs` — la formulation antérieure les rangeait à tort parmi
  les modules sans Actions).
- `.github/workflows/architecture-check.yml:362` : commentaire « dette existante
  (16/18 modules, 57 paires) » → renvoi au comptage de la garde.
- `dev-hub/governance/bounded-context-registry.json` : exception partagée
  `api/app/Core/Shared` (**dossier inexistant**) → **`api/app/Shared`** (dossier
  réel, 12 fichiers PHP). (Les autres corrections du registre — EdgeSync→BC-19,
  BC-25, Core/AI, exceptions Core/* — sont du commit gouvernance précédent.)

### 6.4 Constats supplémentaires pour le chantier (non corrigés — issues)

- **[P1]** `api/app/Modules/HR/Application/Actions/ApplySectorTemplate.php` :
  **fichier PHP corrompu** — pas de `<?php`, pas de namespace ni classe
  (99 lignes de `use` + fonctions nues), 0 référence dans le repo, dans un
  module marqué « ✅ complet ». Corriger depuis l'historique ou supprimer.
- **[P1]** `route-owners.json` (registre machine) : **6 fichiers de routes
  absents**, dont les plus gros — `travelagency.php` (389 l.),
  `restaurantmanager.php` (361 l.), `fuel_station.php` (252 l.),
  `edu_manager.php` (173 l.), `delivery.php` (97 l.), `solutions.php` (27 l.)
  ≈ 1 300 lignes (~32 %) sans owner déclaré ; BC-15 FUEL déclare `routes: []`
  alors que `fuel_station.php` existe. → Régénérer + synchroniser registres.
- **[P2]** `docs/architecture/COMPTABILITE_CONCEPTION.md` (« 18 modules »),
  `docs/architecture/adr/0012-…` (« 19 modules, 6 apps », cite `Training` jamais
  créé), `docs/CONTEXT/02_TECHNICAL_CONTEXT.md`, `docs/GOTO_MARKET/01_PRODUCT/
  PRODUCT_BRIEF.md` (« 18 modules ») : comptages périmés (réel 25/8).
- **[P2]** Références `docs/OPS/BUDGET_AGENTS.md` (≥ 9 fichiers, dont
  `docs/ops/MESURE_KPI.md:34` auto-contradictoire) : le dossier réel est
  `docs/ops/` — casse cassée sur Linux/CI.
- **[P2]** `CHANGELOG.md` (516 Ko > limite 150 Ko de CONVENTIONS §4.3) + structure
  : entrées `[Unreleased]` placées **au-dessus** du header `## [Unreleased]`
  (l.1-12) et notes « ⚠️ En attente de merge » collées au milieu de bulletins
  (résidus de merges union). → Re-structurer + archiver.
- **[P3]** 8 commentaires « Migrated from App\Http\Controllers\Api\V1… »
  résiduels dans des controllers de modules (cosmétique).
- **[P3]** `docs/ARCHITECTURE_STATUS.md` (table reconstruite) : vérifier le
  comptage « tests » par module sur la CI (la colonne repose sur un grep local
  des namespaces).

— Fin du complément (2ᵉ passe, 2026-09-05).


