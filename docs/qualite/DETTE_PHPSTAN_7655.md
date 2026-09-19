# Dette PHPStan gelée — cartographie & plan de résorption (#7655, tranche 1)

> Analyse du 2026-09-19, réalisée par **parsing neon des baselines** (regex
> `message/identifier/count/path`, même méthode que
> `dev-hub/tools/check-phpstan-baseline-debt.sh`) — **sans exécution de
> PHPStan**. Les chiffres sont donc ceux de la dette *actée* dans les
> baselines, pas d'une ré-analyse du code.

## 1. Vue d'ensemble

| Fichier baseline | Lignes | Entrées | Occurrences | Fichiers PHP couverts | Périmètre |
|---|---:|---:|---:|---:|---|
| `api/phpstan-strict-baseline.neon` | 9 963 | 1 634 | 3 267 | ~654 | level 8, `app/Core` + `app/Modules` + `app/Shared` + tests (config `phpstan-strict.neon`) |
| `api/phpstan-baseline.neon` | 3 874 | 578 | 1 042 | 108 | legacy `app/Http`/`app/Services`/tests (config `phpstan.neon`, level max) |
| `api/phpstan-modules-baseline.neon` | 3 182 | 526 | 682 | 113 | legacy modules (config `phpstan-modules.neon`) |
| **Total** | **17 019** | **2 738** | **4 991** | **875** | |

Le « ~2 800 violations » de l'audit correspond aux **2 738 entrées** ; en
occurrences réelles (somme des `count:`), la dette est de **4 991**.

Configs parallèles : `phpstan.neon` (level max, legacy), `phpstan-strict.neon`
(level 8, Core/Modules/Shared), `phpstan-modules.neon` (modules legacy),
`phpstan.ci.neon` (agrégat CI diff-scopé de `tests.yml`). La consolidation des
4 configs est hors périmètre de cette tranche (candidate à une sous-issue).

## 2. Ventilation par type d'erreur (3 baselines confondues)

| # | Identifier | Occurrences | Entrées | Nature |
|---:|---|---:|---:|---|
| 1 | `property.notFound` | 2 097 | 845 | Profond/semi-mécanique — propriétés Eloquent non déclarées : se résout massivement par des annotations `@property` sur les modèles (ide-helper) |
| 2 | `argument.type` | 755 | 391 | Profond — types réels incompatibles, à corriger cas par cas |
| 3 | `property.nonObject` | 402 | 245 | Profond — accès à des mixed/nullables, exige des gardes ou du typage amont |
| 4 | `missingType.iterableValue` | 329 | 329 | **Mécanique** — ajouter `array<...>` en PHPDoc |
| 5 | `method.nonObject` | 267 | 133 | Profond — même famille que property.nonObject |
| 6 | `method.notFound` | 150 | 70 | Semi-mécanique — souvent des scopes/relations Eloquent → `@method` |
| 7 | `argument.templateType` | 132 | 68 | Semi-mécanique — génériques de collections/builders |
| 8 | `missingType.generics` | 122 | 122 | **Mécanique** — préciser les génériques en PHPDoc |
| 9 | `nullsafe.neverNull` | 95 | 71 | **Mécanique** — remplacer `?->` par `->` (le type n'est jamais null) |
| 10 | `assign.propertyType` | 94 | 94 | Profond — typage des propriétés |
| 11 | `return.type` | 89 | 86 | Profond — types de retour incohérents |
| 12 | `encapsedStringPart.nonString` | 58 | 30 | Semi-mécanique — casts explicites dans interpolations |
| 13 | `missingType.return` | 35 | 35 | **Mécanique** — ajouter le type de retour |
| 14 | `variable.undefined` | 31 | 7 | **Profond, risque bug réel** — à traiter en priorité |
| 15 | `method.alreadyNarrowedType` | 30 | 16 | **Mécanique** — assertions redondantes à supprimer |
| 16 | `staticMethod.notFound` | 28 | 21 | Semi-mécanique |
| 17 | `classConstant.notFound` | 26 | 20 | **Profond, risque bug réel** |
| 18 | `cast.string` / `cast.int` / `cast.double` | 61 | 26 | Semi-mécanique |
| 19 | `class.notFound` | 23 | 13 | **Profond, risque bug réel** (classes fantômes) |
| 20 | `binaryOp.invalid` / `arguments.count` | 29 | 12 | **Profond, risque bug réel** |

Lecture clé : **~42 %** des occurrences (`property.notFound` +
`method.notFound` majoritairement sur des modèles Eloquent) se résorbent par
une **campagne d'annotations de modèles** (barryvdh/laravel-ide-helper ou
`@property` manuels) — fort levier, faible risque. **~10 %** sont purement
mécaniques (PHPDoc manquants, nullsafe superflus). Une minorité
(`variable.undefined`, `class.notFound`, `classConstant.notFound`,
`arguments.count`, `binaryOp.invalid` ≈ 110 occ) signale des **bugs latents
probables** et mérite un traitement prioritaire avec revue humaine.

## 3. Ventilation par module (occurrences, 3 baselines confondues)

| Module | Occ | | Module | Occ |
|---|---:|---|---|---:|
| `tests/Feature` | 2 800 | | `app/Modules/Payroll` | 58 |
| `app/Modules/TravelAgency` | 744 | | `routes` | 40 |
| `app/Http` | 327 | | `app/Modules/EdgeSync` | 37 |
| `tests/Unit` | 230 | | `app/Core/Auth` | 32 |
| `app/Modules/Cameras` | 110 | | `app/Modules/Notification` | 28 |
| `app/Modules/HR` | 87 | | `app/Modules/Billing` | 26 |
| `app/Services` | 85 | | `app/Core/Feature` | 25 |
| `app/Modules/RestaurantManager` | 84 | | `app/Modules/EduManager` | 24 |
| `app/Modules/Attendance` | 62 | | `app/Modules/FuelStation` | 58 |

Points saillants :

- **61 % de la dette est dans les tests** (`tests/Feature` + `tests/Unit` =
  3 030 occ) — essentiellement du `property.notFound`/`argument.type` sur les
  réponses JSON et factories. Faible risque de prod, mais masque la vraie
  dette applicative dans les chiffres agrégés.
- **`app/Modules/TravelAgency` = 744 occ** dont 674 dans
  `phpstan-modules-baseline.neon` (669 en `property.notFound`) : un seul
  module concentre l'essentiel de la baseline modules — candidat idéal à une
  campagne d'annotations de modèles dédiée.
- La dette *applicative* hors tests et hors TravelAgency est ≈ **1 200 occ**,
  répartie sur `app/Http` (327), Cameras, HR, Services, RestaurantManager,
  Attendance, FuelStation, Payroll…

## 4. Top 20 des fichiers les plus endettés (occurrences)

| # | Fichier | Occ |
|---:|---|---:|
| 1 | `tests/Feature/AnnouncementControllerTest.php` | 99 |
| 2 | `tests/Unit/Services/FeatureRegistryTest.php` | 82 |
| 3 | `tests/Feature/ConversationControllerTest.php` | 80 |
| 4 | `tests/Feature/Attendance/AttendanceAnomaliesTest.php` | 73 |
| 5 | `tests/Feature/ExpenseClaimControllerTest.php` | 64 |
| 6 | `tests/Feature/GrowthModuleTest.php` | 56 |
| 7 | `tests/Feature/OrgChartControllerTest.php` | 49 |
| 8 | `tests/Feature/WebManagerPagesTest.php` | 47 |
| 9 | `tests/Feature/PlatformCompanyHealthApiTest.php` | 45 |
| 10 | `tests/Feature/EmployeeLoanControllerTest.php` | 43 |
| 11 | `tests/Feature/Attendance/AttendanceRegularityTest.php` | 40 |
| 12 | `tests/Feature/PlatformImpersonationControllerTest.php` | 38 |
| 13 | `tests/Unit/Services/FeatureDetectorTest.php` | 38 |
| 14 | `app/Services/FeatureDetector.php` | 38 |
| 15 | `routes/console.php` | 38 |
| 16 | `tests/Feature/PublicCareerControllerTest.php` | 35 |
| 17 | `tests/Feature/ScheduleControllerTest.php` | 35 |
| 18 | `tests/Feature/BillingControllerTest.php` | 34 |
| 19 | `tests/Feature/Security/GoogleAuthGlobalLookupTest.php` | 34 |
| 20 | `tests/Feature/Evaluations/EvaluationWorkflowTest.php` | 34 |

17/20 sont des tests. Les fichiers *applicatifs* les plus endettés :
`app/Services/FeatureDetector.php` (38), `routes/console.php` (38), puis la
longue traîne TravelAgency.

## 5. Gardes CI existantes (état vérifié 2026-09-19)

Il n'existe pas de check nommé littéralement « PHPStan (strict,
no-baseline-growth) » ; la fonction est assurée par deux checks **requis** sur
`main` :

1. **`PHPStan — Strict (Core/Modules/Shared, level 8)`**
   (`architecture-check.yml`, job `phpstan-strict`) : exécute PHPStan avec la
   baseline strict → **bloquant sur tout delta** (toute nouvelle violation
   level 8 non baselinée fait échouer la PR).
2. **`Module Structure Validator`** (`architecture-check.yml`, job
   `module-structure-check`) exécute :
   - `check-phpstan-baseline-delta.sh` (PA2-ARCH-005) : cliquet
     module-scopé — un module touché ne peut pas voir son compte baseline
     augmenter ;
   - `check-phpstan-baseline-debt.sh guard` (#5448) : cliquet **global** —
     aucune nouvelle entrée, aucun `count:` augmenté, sur les 3 baselines,
     quel que soit le module (déplacements de fichiers tolérés).

### Trous identifiés (comblés par cette tranche)

| Trou | Détail | Correctif tranche 1 |
|---|---|---|
| **Nouveaux fichiers baseline** | La liste des baselines est codée en dur dans les 2 scripts : une PR pouvait ajouter `api/phpstan-<x>-baseline.neon` + l'inclure dans une config, dette invisible pour le cliquet. | Le guard #5448 échoue désormais si un fichier `*baseline*.neon` non répertorié existe sous `api/` à HEAD. |
| **Pas de suivi chiffré** | Aucun total n'était publié ; la trajectoire était invisible. | Le guard écrit dans le résumé du job (`GITHUB_STEP_SUMMARY`) le total d'occurrences base → head par fichier et global. |
| **Pas de cliquet sur le TOTAL** | Le cliquet par entrée suffisait sauf pendant les fenêtres d'exception. | Le guard échoue si le total global d'occurrences augmente au-delà des nouvelles entrées explicitement tolérées par une fenêtre de gouvernance datée. |
| **Fenêtre d'exception ouverte** | `dev-hub/governance/phpstan-baseline-exceptions.json` tolère les *nouvelles entrées* dans la baseline strict **jusqu'au 2026-10-05** (#6818). | Non modifiée (gouvernance PM) ; le suivi chiffré rend son coût visible. À ne **pas** reconduire après expiration. |

## 6. Plan de résorption par lots

Contrainte transverse : chaque lot **doit être exécuté avec PHPStan en local
ou via la CI** (régénération partielle de la baseline après correction) — non
réalisable depuis cette sandbox (pas de PHP/composer). Cette tranche 1 ne
corrige donc **aucune violation** ; elle fournit la carte et le cliquet.

Budget recommandé (repris de l'audit) : **−20 entrées minimum par PR touchant
un module endetté**, trajectoire suivie via le résumé chiffré du guard.

| Lot | Contenu | Effort | Risque | Gain estimé |
|---|---|---|---|---|
| **A — Bugs latents (priorité)** | `variable.undefined` (31), `class.notFound` (23), `classConstant.notFound` (26), `arguments.count` (14), `binaryOp.invalid` (15) : chaque cas est potentiellement un bug réel. Revue humaine obligatoire. | M | Correctif = changement de comportement possible → tests requis | ~110 occ + fiabilité |
| **B — Annotations de modèles Eloquent** | `property.notFound` (2 097) + `method.notFound` (150) : générer les PHPDoc `@property`/`@method` (ide-helper ou manuel) sur les modèles des modules TravelAgency, Cameras, HR, RestaurantManager, puis régénérer les baselines. | M (outillé) | Faible (PHPDoc uniquement) | jusqu'à ~2 200 occ (~45 %) |
| **C — Mécanique PHPDoc/nettoyage** | `missingType.iterableValue` (329), `missingType.generics` (122), `missingType.return` (35), `nullsafe.neverNull` (95), `method.alreadyNarrowedType` (30). Automatisable en grande partie (rector/phpstan-fixer). | S–M | Très faible | ~610 occ |
| **D — Tests Feature/Unit** | 3 030 occ dans les tests : typage des helpers de test, `assert*` typés, factories génériques. À découper par domaine de test (Attendance, Platform, Billing…). | L | Nul en prod | ~3 030 occ |
| **E — Typage profond applicatif** | `argument.type` (755), `property.nonObject` (402), `method.nonObject` (267), `assign.propertyType` (94), `return.type` (89) hors tests : cas par cas, par module, en commençant par `app/Http` (327) et `app/Services`. | L | Moyen (refactors) | ~1 000 occ |
| **F — Gouvernance configs** | Fusionner les 4 configs PHPStan en une config + une baseline unique (ou includes hiérarchiques documentés) ; clore la fenêtre #6818 à échéance. | M | CI | lisibilité |

Ordre suggéré : **A → B → C** (gains rapides, ~55 % de la dette), puis D et E
en parallèle par module, F en clôture. Chaque lot = sous-issue dédiée
référencée sur #7655.

## 7. Suivi

- Rapport détaillé à la demande : `dev-hub/tools/check-phpstan-baseline-debt.sh report api`
  (pur bash/python3, aucun PHP requis).
- Trajectoire chiffrée : résumé du job `Module Structure Validator` sur chaque PR.
