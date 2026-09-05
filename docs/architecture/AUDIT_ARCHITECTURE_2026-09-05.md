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

— Fin du rapport PM Architecture (2026-09-05). Prochaines passes PM : voir §3.
