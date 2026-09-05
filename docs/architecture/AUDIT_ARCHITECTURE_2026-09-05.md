# Audit Architecture — Rapport consolidé des PM (2026-09-05)

> **Rôles** : PM Architecture (coordination) + PM audit « vérité & conformité ».
> **Branche unique de travail des 4 PM** : `pm/audit-architecture-2026-09-05` — TOUS les
> PM committent sur CETTE branche ; PR d'intégration unique : **#6830** (la PR #6832 a été
> fusionnée ici — voir §6).
> **Périmètre** : véracité docs vs dépôt, structure DDD (fichiers/dossiers à leur place),
> frontières des bounded contexts, règles de collaboration dev, « chantier architectural ».
> **Méthode** : toute correction chiffrée a été **mesurée sur le disque** (find/grep/wc/ls),
> jamais déduite d'un autre document ; audits croisés + recoupement manuel. Commit de
> référence audité : `357a2b040` (main, 2026-09-05).

---

## 1. Ce qui est VRAI sur main (vérifié — à ne pas « corriger »)

- **25 modules DDD** sous `api/app/Modules/` ; **8 dossiers legacy supprimés**
  (`app/Models`, `app/Services`, `app/Http/Controllers/Api/V1`, `app/Http/Requests`,
  `app/Traits`, `app/Attributes`, `app/Enums`, `app/DTOs`) — 0 référence résiduelle.
- `app/Shared/` : tout le contenu documenté est présent (+ 3 extras non documentés :
  Contracts/Notification/EmployeeNotifier, Services/InboundWebhookVerifier,
  Models/Language).
- Garde d'isolation **#5584 verte et synchronisée** (55 paires actées = exactement le
  code : 0 ligne morte, 0 trou). Garde CRM **active** (module CRM = 196 fichiers) et
  verte, 0 exemption. Garde de pureté des couches #6568 en place (allowlist 43+).
- **Registre BC** : 26 BC continus (BC-01…BC-26), 100 % des 25 modules + `app/AI`
  mappés, garde MAT-001 verte (chemins/routes/migrations/CODEOWNERS).
- Gates CI requises : Backend Coverage 65 % (+ 80 % Payroll bloquant), PHPStan Strict
  L8 (delta baseline), Module Structure Validator, ESLint+TS, actionlint — toutes
  câblées. 54 workflows (cartographie `.github/workflows/README.md` complète).
- **Garde de parité docs-architecture** : `dev-hub/tools/check-architecture-docs-parity.sh`
  → OK (25 modules × 4 docs, 0 fantôme).

## 2. Faux positifs corrigés dans cette passe (commits A→F + fusion #6832)

| # | Constat (ligne fausse / obsolète) | Correctif livré |
|---|-----------------------------------|-----------------|
| 1 | CONVENTIONS.md : **18 modules** actifs (7 manquants), section « Verbes HTTP » dupliquée, numérotation désordonnée, `D` orphelin, i18n `resources/lang/` inexistant | 25 modules, dédup, renumérotation, chemin `api/lang/` |
| 2 | ARCHITECTURE.md racine : arbre mobile incomplet (`leopardo_travel_agent`), `marketing/` supprimé non reflété, « 40 pipelines » (réel 54), shims legacy « encore présents », alias `App\Services\TenantManager` | Arbre = réalité (8 packages mobiles, `site/`, 54 workflows), état shims réécrit |
| 3 | api/ARCHITECTURE.md : ligne `Modules/Restaurant` fausse ; « 60 JsonResource » (réel 67) ; statuts « ✅ complet » **surévalués** (Payroll, Planning, Expense, Recruitment, Fleet, FuelStation **+** Billing, Cameras, EdgeSync, Delivery, EduManager, Onboarding, Growth, RestaurantManager, TravelAgency) | Ligne Restaurant corrigée ; 67 ; statuts réalignés sur le contenu réel des couches (table §tableau) |
| 4 | docs/ARCHITECTURE_STATUS.md : table **corrompue par merges union** (CRM ×2, Accounting ×3, RestaurantManager ×3…) | Table reconstruite (25 lignes uniques), méthode reproductible (sous-couche ≥ 1 PHP ; Tests = fichiers référençant le namespace) |
| 5 | Registre BC : `EdgeSync` en double propriété (BC-14+BC-19) ; doublon `RestaurantManager planned` (BC-25) ; `api/app/Core/AI` non déclaré ; 4 dossiers Core transversaux non couverts | EdgeSync → BC-19 seul ; BC-25 dédoublonné ; Core/AI → BC-23 actif + CODEOWNERS ; exceptions Core/{Solutions,Notifications,Privacy,Seed} |
| 6 | front/mobile_apps/README.md : puce `leopardo_accounting` dupliquée décrivant l'app marketing ; liste CI incomplète | Puce → `leopardo_marketing` ; liste CI = 8 packages réels |
| 7 | README.md racine : lien mort programme CRM | Lien → `MODULE_CRM_INTERNE_CLIENT.md` |
| 8 | Guide module + stub : couche `Domain/Contracts` absente du squelette | Ajoutée (guide + `stubs/module-template/`) |
| 9 | `.github/workflows/README.md` : `edge-ci.yml`, `pages-deploy.yml` non documentés | 54/54 documentés |
| 10 | Chemins `docs/OPS/…` invalides (dossier réel : `docs/ops/`) dans 9 fichiers (render.prod.yaml, DEVELOPMENT.md, PLAN_60_JOURS.md, ARCHITECTURE_CICD.md, docs ops/pilotes/plan/GESTION_PROJET) | Chemins corrigés |
| 11 | docs/ARCHITECTURE_CICD.md : « deploy-main.yml → Render production » faux | deploy-main = dev/test ; PROD = tag → deploy-prod.yml |
| 12 | CODEOWNERS : lignes dupliquées (6) | Dédupliquées (+ ligne Core/AI) |
| 13 | `leopardo_travel_agent` : 25 fichiers `build/` trackés dans git (artefacts Flutter) | Désindexés + `.gitignore` ajouté |

**Aucun changement de code applicatif.**

## 3. Chantier restant — dette structurelle mesurée

> Règle anti-doublon : **1 item = 1 issue (label BC) = 1 branche `fix/<issue>-<slug>`**
> (protocole #2400). Aucune de ces dettes ne bloque la CI (les gardes vérifient
> l'existence des couches, pas leur contenu).

### 3.1 Couches `Application/` (Actions/DTOs) à créer — modules mesurés vides/absents
| Module | État mesuré | Action |
|---|---|---|
| `Expense` | `Application/` absente ; encore dans l'exemption CI (`FACADE_ONLY_MODULES`) | Créer la couche Application (écritures comptables) puis retirer de l'exemption |
| `Payroll` | `Application/` = 1 Service, 0 Action (module de 133 PHP) | Créer les Actions (cf. extraction #5591) |
| `Planning` | `Application/` vide (0 PHP) | Créer les Actions ; reste propriétaire canonique des modèles Absence/Expense |
| `Recruitment` | `Application/` vide (0 Action) | Créer les Actions |
| `Fleet` | `Application/` et `Infrastructure/` vides (0 PHP) ; contrats `Domain/Contracts/{Trip,Vehicle}RepositoryInterface` sans implémenteur identifié | Peupler ou supprimer les contrats morts |
| `FuelStation` | `Application/` vide (0 PHP) malgré 172 PHP ailleurs | Créer les Actions |
| `EduManager`, `Delivery`, `TravelAgency`, `RestaurantManager` | `Application/DTOs` (et/ou Actions) = squelettes | Étoffer les couches (verticales récentes) |
| `Growth` | Pas de `Domain/Models` ; `Infrastructure/` vide | Compléter (tests aussi, cf. 3.4) |
| `Onboarding` | Pas de `Domain/Models` | Compléter ou acter |

### 3.2 Placement / conventions
- `Cameras` : 4 modèles Eloquent à la racine de `Domain/` (`Domain/Camera.php`,
  `CameraAccessLog.php`, …) — namespace PSR-4 correct mais déviation du template
  `Domain/Models/`. Décision : aligner ou acter.
- Controllers directement sous `Interfaces/Api/V1/` (pas de sous-dossier `Controllers/`) :
  `Cabinet/.../CabinetDocumentController.php` (+ Requests), `Attendance/.../KioskEnrollmentController.php`.
- `Restaurant` : `Interfaces/` vide par conception — les routes publiques sont servies
  par `Core\Solutions` ; module = fournisseur de contenu (Domain + Provider).
- Doublon de classe non routé : `Core\Solutions\Interfaces\...\PlatformSolutionSurveyStatsController`
  (la version routée est `Modules\Platform\...`) — supprimer après vérification.

### 3.3 Frontières de BC à trancher (décisions d'architecture requises)
- Agrégat `Employee` canonique dans `Core/Auth` (importe les Domain de 5 modules) —
  chantier **#5584 non clos** malgré des docs le présentant comme tel (361 consommateurs).
- `app/Shared/` viole « Shared ne dépend de rien » : `Traits/Approvable.php` →
  `Modules\Attendance`, `BelongsToCompany` → `Core/Tenant`,
  `Auditable`/`EmployeeNotifier` → `Core/Auth`.
- Interfaces dupliquées : `SolutionManifest` ×4 (Core/Solutions, Delivery,
  RestaurantManager, TravelAgency) ; `PaymentGatewayInterface` ×3 signatures
  divergentes (Accounting, RestaurantManager, TravelAgency).
- Couplage bidirectionnel Payroll⇄Billing en Domain
  (`Payroll/Domain/Models/{Commission,Payment}` ↔ `Billing/Domain/Models/{Invoice,Partner}`).

### 3.4 Zones legacy / non documentées
- `app/Policies/` (41 classes) et `app/Contracts/` (6 fichiers, 53 consommateurs)
  absents des docs d'architecture → documenter ou migrer (registre BC : Policies et
  Contracts/* sont couverts par des BC ? vérifier ligne par ligne).
- `app/AI` importe des Domain de modules hors périmètre de la garde d'isolation ;
  `app/Core/AI` désormais déclaré (BC-23) — documenter la répartition AI vs Core/AI.
- Routes intra-modules chargées par providers (`Attendance/routes/geo.php`,
  `HR/routes/candidate_hiring.php`, `EdgeSync/routes/api.php`) — hors tableau
  `routes/modules/` (à acter ou documenter).
- Tests dédiés manquants (aucun dossier de test référençant le namespace) : **Growth
  (0)**, faible Platform (8) / Onboarding (2) ; `Feature/Restaurant` ne référence pas
  `RestaurantManager` (ses tests = 59 fichiers mais ailleurs).
- PHPStan : les chiffres cités dans les docs (« 1297 erreurs ») doivent être recalculés
  par une exécution réelle — mesure du PM audit : baseline strict ≈ 1 667 entrées
  / 3 317 erreurs (à confirmer par un run CI phpstan).

## 4. Coordination multi-PM / multi-dev — état et manques

### Déjà en place (solide, vérifié)
- Registre machine-readable **26 BC** + CODEOWNERS aligné + garde MAT-001.
- Protocole branche = lock + claim marker (#2400), « 1 agent par BC », protocole lot
  `bc/<code>-<slug>`, gardes `branch-hygiene`, `pr-issue-guard`, `issue-governance`.
- Convention commits `type(scope):`, CHANGELOG obligatoire, `Closes #N` dans le corps
  de PR. Spécifications obligatoires avant module (`docs/specifications/`).

### Manques (propositions — décision requise du fondateur)
| # | Manque | Proposition |
|---|---|---|
| M1 | Le registre BC ne couvre que `api/` — `front/`, `edge/`, `dev-hub/`, `shared/i18n/`, `leopardo_core` sans propriétaire | Étendre le registre avec des surfaces partagées (ex. `shared_surfaces`) + garde CI |
| M2 | CODEOWNERS documentaire uniquement (0 review requise) | Activer `require_code_owner_reviews` dès qu'un 2ᵉ mainteneur rejoint |
| M3 | Pas de règle « surface partagée = écrivain unique à la fois » | Label `surface-shared` + annonce d'intention sur l'issue avant modification de `leopardo_core`/`shared/i18n` |
| M4 | Commits sans scope BC ni n° d'issue | Convention `feat(BC-XX):` + n° d'issue dans le nom de branche (étendre au-delà de CRM) |
| M5 | Inventaires mobiles divergents entre docs (corrigés ce jour) | `melos.yaml` = source canonique ; les docs pointent vers lui |
| M6 | Pas de branche unique effective au départ (PR #6832 ouverte en parallèle de #6830) | Acté : fusion dans `pm/audit-architecture-2026-09-05` ; PR #6832 fermée avec renvoi |

## 5. Gardes locales exécutées (2026-09-05)
- `dev-hub/tools/check-bounded-context-registry.sh` → ✓ 26 BC (chemins/routes/migrations/CODEOWNERS).
- `dev-hub/tools/check-module-isolation.sh` → ✓ 55 violations connues (allowlist synchrone).
- `dev-hub/tools/check-crm-boundary-imports.sh` → ✓ CRM OK (196 fichiers).
- `dev-hub/tools/check-architecture-docs-parity.sh` → ✓ 25 modules × 4 docs, 0 fantôme.
- PHP/Composer/Flutter absents du sandbox : les jobs lourds (PHPUnit, PHPStan, Pint,
  builds Flutter) n'ont pas été rejoués localement — ils tournent sur la PR #6830
  (checks requis).

## 6. Fusion des PR parallèles (chef de projet)
Les PM ont initialement ouvert **deux PR** pour le même audit (#6830 sur
`pm/audit-architecture-2026-09-05`, #6832 sur `audit/architecture-2026-09-05`) — doublon
contraire au protocole #2400. Décision : la branche `pm/audit-architecture-2026-09-05`
reste LA branche unique ; le contenu de #6832 (13 correctifs complémentaires : chemins
`docs/ops`, CODEOWNERS dédupliqué, désindexation `build/` travel_agent, statuts modules
🔶, rapport dette mesurée) y a été **fusionné** (merge `pm6832`, historique conservé).
PR #6832 fermée avec renvoi vers #6830.

## 7. Fichiers exacts modifiés par cette passe
`CONVENTIONS.md`, `AGENTS.md`, `ARCHITECTURE.md`, `api/ARCHITECTURE.md`,
`docs/ARCHITECTURE_STATUS.md`, `README.md`, `DEVELOPMENT.md`, `PLAN_60_JOURS.md`,
`front/mobile_apps/README.md`, `front/mobile_apps/leopardo_travel_agent/.gitignore` (+
25 fichiers `build/` désindexés), `docs/ARCHITECTURE_CICD.md`, `docs/ops/MESURE_KPI.md`,
`docs/pilotes/{KPI_GATE_2026-08-20,RETRO_2026-08-24}.md`, `docs/plan/PLAN_100PCT.md`,
`docs/GESTION_PROJET/{HANDOFF_OPERATIONNEL_R6,RUNBOOK_RENDER_WORKERS}.md`,
`render.prod.yaml` (commentaires), `docs/architecture/module-creation-guide.md`,
`api/stubs/module-template/Domain/Contracts/.gitkeep`, `.github/workflows/README.md`,
`dev-hub/governance/bounded-context-registry.json`, `CODEOWNERS`, `CHANGELOG.md`.

— Fin du rapport consolidé (2026-09-05). Prochaines passes : §3.

---

## 7. Passe 2 (PM correctifs, 2026-09-05) — réintégrée par cherry-pick

Commits du PM « vérité & conformité » (ex-PR #6832, refermée — contenu réintégré ici) :
- `78a84148c` **ci** : garde CRM passée en mode 100755 (`check-crm-boundary-imports.sh`) —
  l'étape `architecture-check.yml` l'invoque sans `bash` → Permission denied (exit 126) sinon.
- `07e7d94e2` **docs** : chemins égarés — `DEVELOPMENT.md` arbre `docs/API/` → `docs/api/` ;
  `TENANT_CONTEXT_CONVENTIONS.md` : doublon legacy `app/Traits/BelongsToCompany.php` présenté
  comme vivant → supprimé (#6565) ; `PHPSTAN_BASELINE.md` : notice de péremption du snapshot
  2026-08-25 (`SmartAttendance` supprimé #5356 ; baseline réelle 1667 entrées au 2026-09-05).
- `6c7f2a12d` **fix(hr)** : `ApplySectorTemplate` (Action HR) sans façade `DB` — injection
  `Illuminate\Database\Connection` ; ligne retirée de `layer-purity-allowlist.txt` (128 → 127).
  Garde `check-layer-purity.sh` ✅.

Issues créées pour le reste connu (1 item = 1 issue) :
- **#6844** — Shared → Core/Modules : 6 imports transverses (Approvable, Auditable,
  BelongsToCompany, EmployeeNotifier) : acter la tolérance ou inverser la dépendance.
- **#6845** — Doublons de casse `api/tests/{Feature,Unit}/CRM` vs `Crm` (collision FS
  insensible à la casse) — unifier sous `CRM`, baseline phpstan à régénérer (PR dédiée).

Mesures complémentaires passe 2 : `declare(strict_types=1)` = 3351/3646 (92 %) — retrofit
suivi par #1412/#1096 ; `check-application-layer-placement.sh` : 29 Services/Jobs sous
`Application/` (issue #6571, en migration, non câblée CI) ; mobile : riverpod 8/8, 0 bloc/provider.
