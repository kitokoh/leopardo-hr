# Architecture DDD — État des modules

> Mis à jour le **2026-09-05** (audit PM architecture — table reconstruite depuis le
> filesystem + gardes CI ; les lignes dupliquées/contradictoires issues de merges union
> ont été purgées, cf. narrative #6824). Nettoyage legacy terminé (PR #824, #1728).

## 1. Tableau de l'état DDD — 25 modules actifs

> **Méthode (reproductible)** : chaque colonne correspond à une sous-couche canonique ;
> `✅` = la sous-couche existe et contient au moins 1 fichier PHP (`.gitkeep` exclus) ;
> `✗` = absente ou vide ; `—` = non applicable (façade documentée). Colonne **Tests** =
> nombre de fichiers `api/tests/Feature` + `api/tests/Unit` référençant le namespace du
> module (commande : `grep -rl 'App\Modules\<M>\' api/tests/Feature api/tests/Unit | wc -l`) ;
> `0` signifie qu'aucun fichier de test n'importe le namespace (couverture possible via
> routes HTTP). Vérifié au commit `357a2b040` (2026-09-05).

| Module            | Domain | Contracts | Exceptions | Application | DTOs | Infra | Interfaces | Providers | Tests |
|-------------------|:------:|:---------:|:----------:|:-----------:|:----:|:-----:|:----------:|:---------:|:-----:|
| **Absence**       | — | — | — | — | — | — | ✅ | ✅ | 0 |
| **Accounting**    | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 39 |
| **Attendance**    | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 99 |
| **Billing**       | ✅ | ✅ | ✅ | ✅ | ✗ | ✅ | ✅ | ✅ | 44 |
| **Cabinet**       | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 8 |
| **Cameras**       | ✗ | ✅ | ✅ | ✅ | ✗ | ✅ | ✅ | ✅ | 5 |
| **CRM**           | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 22 |
| **Delivery**      | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 19 |
| **EdgeSync**      | ✅ | ✗ | ✗ | ✅ | ✗ | ✅ | ✅ | ✅ | 10 |
| **EduManager**    | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 39 |
| **Expense**       | ✅ | ✗ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 1 |
| **Fleet**         | ✅ | ✅ | ✅ | ✗ | ✗ | ✗ | ✅ | ✅ | 3 |
| **FuelStation**   | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 29 |
| **Growth**        | ✗ | ✅ | ✅ | ✅ | ✅ | ✗ | ✅ | ✅ | 0 |
| **HR**            | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 56 |
| **Marketing**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 21 |
| **Notification**  | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 45 |
| **Onboarding**    | ✗ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 2 |
| **Payroll**       | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 169 |
| **Planning**      | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 78 |
| **Platform**      | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 8 |
| **Recruitment**   | ✅ | ✅ | ✅ | ✗ | ✗ | ✅ | ✅ | ✅ | 4 |
| **Restaurant**    | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✅ | 2 |
| **RestaurantManager** | ✅ | ✅ | ✅ | ✅ | ✗ | ✅ | ✅ | ✅ | 59 |
| **TravelAgency**  | ✅ | ✅ | ✅ | ✅ | ✗ | ✅ | ✅ | ✅ | 101 |

### Notes de lecture (2026-09-05)

- **Absence** : façade HTTP sur `Planning` (Interfaces + Providers uniquement —
  dérogation PA2-ARCH-002, voir `api/ARCHITECTURE.md`). **Expense** : module DDD
  partiel depuis #5235 (dérogation PA2-ARCH-011 résorbée) — couche `Application`
  absente, modèles de notes de frais sous contrat `Planning`.
- **EdgeSync** : structure spécialisée synchro/offline — `Domain/Contracts`,
  `Domain/Exceptions` et `Application/DTOs` absents (acté, voir `api/ARCHITECTURE.md`).
- **Restaurant** : module *fournisseur de contenu Solutions* — `Domain/` non vide mais
  hors sous-dossiers canoniques (`Domain/Solution/RestaurantManifest.php`,
  `Domain/Survey/RestaurantSurvey.php`) ; `Application/`, `Infrastructure/`,
  `Interfaces/` = squelettes `.gitkeep`. La surface HTTP (webhooks livraison, shop)
  vit dans `RestaurantManager` (inline `routes/api.php`) et les surveys publics dans
  `Core\Solutions` (`routes/modules/solutions.php`).
- **Cameras** : les modèles Eloquent sont à la racine de `Domain/`
  (`Domain/Camera.php`, `CameraAccessLog.php`, …) — pas de sous-dossier `Domain/Models`
  (écart structurel à résorber, cf. §5).
- **Billing, Delivery, EdgeSync, EduManager, Expense, Fleet, FuelStation, Payroll,
  Planning, Recruitment, RestaurantManager, TravelAgency** : couche `Application`
  (Actions/DTOs) absente ou vide — les contrôleurs délèguent encore majoritairement à
  des services `Infrastructure`. C'est le premier chantier d'enrichissement DDD (§5).
- **Tests = 0** (Absence, Growth) : aucun fichier de test n'importe le namespace du
  module — à compléter (Growth : roadmap §5).
- La colonne « Modules 100 % complets » (8 modules avec les 8 sous-couches peuplées :
  Accounting, Attendance, Cabinet, CRM, HR, Marketing, Notification, Platform) ne doit
  pas être confondue avec le validateur CI, qui ne contrôle que l'existence des 5
  couches racines.

## 2. Routes — État de la migration

| Fichier | Legacy avant | Legacy après | Statut |
|---------|:-----------:|:------------:|:------:|
| `routes/modules/rh.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/absence.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/expense.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/payroll_engine.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/cameras.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/cabinet.php` | 3 | **0** | ✅ Phase 3–4 |
| `routes/modules/tracking.php` | 6 | **0** | ✅ Phase 3–4 |
| `routes/modules/billing.php` | 3 | **0** | ✅ Phase 3–4 |
| `routes/modules/dashboard.php` | 4 | **0** | ✅ Phase 3–4 |
| `routes/modules/integrations.php` | 4 | **0** | ✅ Phase 3–4 |
| `routes/modules/planning.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/hr_app.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/growth.php` | 2 | **0** | ✅ Phase 3–4 |
| `routes/modules/sso.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/user.php` | 2 | **0** | ✅ Phase 3–4 |
| `routes/api.php` | 25 | **0** | ✅ Phase 4 |
| `routes/web.php` | 11 | 11 | ⚠️ Web controllers (hors scope API DDD) |
| `routes/ai.php` | 1 | **0** | ✅ Phase 4 |

**Total API routes : 0 import legacy** `App\Http\Controllers\Api\V1` (vérifié 2026-09-05).

## 3. Métriques réelles (2026-09-05)

| Indicateur | Valeur |
|-----------|--------|
| Modules DDD sous `api/app/Modules/` | **25** (vérifié `ls`) |
| Modules avec les 8 sous-couches canoniques peuplées | **8** (Accounting, Attendance, Cabinet, CRM, HR, Marketing, Notification, Platform) |
| Modules avec `Domain/Models` peuplé | **20** |
| ServiceProviders modules enregistrés dans `bootstrap/providers.php` | **25/25** |
| Imports legacy (`App\Http\Controllers\Api\V1`, `App\Services`, `App\Models`…) | **0** |
| Fichiers PHP modules | 2 149 |
| Import croisés inter-modules (dette actée #5584, allowlist) | 55 paires (dont 12 depuis des couches `Domain/`) |
| Fichiers de test référençant un namespace module (Feature+Unit) | ≈ 863 comptages module (un fichier peut en référencer plusieurs) |
| Registre BC (MAT-001) | 26 BC (BC-01…BC-26), 100 % modules mappés |

## 4. CI/CD — Coverage Gate

**Activé comme required check** sur `main` :
- Check : `Backend Coverage (PHP 8.4 + PostgreSQL 16)` — seuil **65 %** (global,
  `coverage-gate.yml`) ; **80 %** Payroll bloquant (`payroll-ci.yml`, job coverage).
- Autres checks requis : `PHPStan — Strict (Core/Modules/Shared, level 8)`,
  `Module Structure Validator`, `Frontend — ESLint + TypeScript`, `actionlint (+ shellcheck)`.
- PHPStan : `phpstan-modules.neon` (niveau 5, bloquant) + `phpstan-strict.neon`
  (niveau 8, gate sur delta via baseline — jamais élargir).

## 5. Chantier restant

| Item | Priorité | Effort | Statut |
|------|:--------:|:------:|:------:|
| Supprimer `app/Models/` doublons | P1 | Élevé | ✅ Fait — répertoire supprimé, 92 modèles migrés |
| Finaliser `app/DTOs/` racine | P1 | Faible | ✅ Fait — répertoire supprimé |
| Peupler `app/Shared/` (Traits/Attributes/Enums) | P2 | Moyen | ✅ Fait |
| Migrer `Core/Tenant/` (TenantManager canonique) | P2 | Moyen | ✅ Fait |
| Supprimer shims legacy (`app/Services/`, `app/Http/Requests/`, `app/Traits/`, `app/Attributes/`, `app/Enums/`) | P1 | Moyen | ✅ Fait (2026-08-11 → 2026-08-31) — 0 référence restante |
| PHPStan niveau 5 via `phpstan-modules.neon` | P2 | Moyen | ✅ Fait — gate bloquant CI |
| OpenAPI canonique (`api/openapi.yaml` + miroir `dev-hub`) | P2 | Faible | ✅ Fait — Redocly 0 erreur |
| i18n backend `fr/en/ar/tr` via `api/lang/` | P3 | Moyen | ✅ Fait |
| **Enrichir les couches `Application/` (Actions/DTOs)** des modules sans sous-couche peuplée (Delivery, EduManager, Expense, Fleet, FuelStation, Payroll, Planning, Recruitment, RestaurantManager, TravelAgency, Billing/DTOs, Growth/Infra) | P1 | Élevé | ⏳ Chantier ouvert — extractions d'Actions déjà en cours (ex. #6569 Platform) |
| **Déplacer les modèles `Cameras` vers `Domain/Models/`** (4 classes à la racine de `Domain/`) | P2 | Faible | ⏳ À faire |
| **Supprimer le doublon `Core\Solutions\Interfaces\Api\V1\PlatformSolutionSurveyStatsController`** (non routé — la version routée est `Modules\Platform`) | P2 | Faible | ⏳ À faire |
| **Réduire la dette d'isolation inter-modules** (55 paires allowlistées #5584 ; 12 depuis `Domain/`) | P1 | Élevé | ⏳ En cours — refactors #5591, #6588… |
| Tests Feature pour Growth (0 fichier référençant le namespace), Platform (8), Onboarding (2) | P1 | Moyen | ⏳ À faire (Growth prioritaire) |
| routes/web.php — Web controllers (hors scope ADR actuel) | P3 | Faible | ⏳ À faire |
| Check externe « Workers Builds / Vercel » (quota) | P3 | Faible | ⏳ Décision à acter (non requis — leçons AGENTS.md 2026-08-17) |
| Registre BC : chemins `planned` fantômes (`api/app/Solutions/*`, `Modules/Documents`, `Modules/Reporting`) et couverture `Core/*` | P2 | Faible | 🔄 Corrigé partiellement 2026-09-05 (doublons EdgeSync/BC-25, `Core/AI`) — chemins planned conservés (roadmap) |
| Event Sourcing Absence + Expense (CQRS) | P4 | Très élevé | ⏳ Hors périmètre court terme |
| PostgreSQL RLS (remplace filtres `company_id`) | P4 | Très élevé | ⏳ Phase 3 produit |

### Nettoyage legacy — bilan cumulé (PR #824 + phase 2, vérifié 2026-09-05)

✅ 90 controllers `app/Http/Controllers/Api/V1/` supprimés (2026-07-01)
✅ 26 services `app/Services/` supprimés + 17 shims backward-compat (2026-08-11, #1728)
✅ 92 modèles `app/Models/` migrés vers les modules — répertoire supprimé
✅ 64 FormRequests `app/Http/Requests/` migrés vers les modules — répertoire supprimé (2026-08-31)
✅ 4 couches `Infrastructure/` créées (Growth, Platform, Onboarding)
✅ `app/Shared/` peuplé (Traits, Attributes, Enums, Exceptions, DTOs)
✅ 25/25 ServiceProviders de modules enregistrés dans `bootstrap/providers.php`
