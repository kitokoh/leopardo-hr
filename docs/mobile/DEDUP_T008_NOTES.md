# Chantier T008 (#2601) — Notes de chantier, risques, lot suivant

> **Issue** : #2601 — Dé-duplication `leopardo_hr`/`leopardo_manager`.
> **Branche** : `refactor/2601-mobile-dedup`. **Date** : 2026-08-20.
> **Inventaire complet** : [`DEDUP_T008_INVENTAIRE.md`](./DEDUP_T008_INVENTAIRE.md).

## Ce qui a été fait (lot 1 — extraction sûre)

Déplacement de **10 fichiers de test byte-identiques** (HR == Manager == destination) depuis
`leopardo_hr/test/` et `leopardo_manager/test/` vers **`leopardo_core/test/`** (mêmes chemins
relatifs), conformément au critère strict du chantier (byte-identique + imports core-only) :

| Fichier extrait (dans `leopardo_core/test/`) |
|---|
| `features/attendance/history_screen_test.dart` |
| `features/auth/welcome_screen_test.dart` |
| `models/approval_test.dart` |
| `models/contract_test.dart` |
| `models/onboarding_step_test.dart` |
| `models/sync_models_test.dart` |
| `models/training_enrollment_test.dart` |
| `models/vehicle_position_test.dart` |
| `widget_test.dart` |
| `widgets/mobile_surface_test.dart` |

**Impact net** : −10 copies dans chaque app (20 fichiers supprimés au total), +10 fichiers dans
le package partagé. Les imports de ces fichiers (15 cibles uniques, toutes dans
`leopardo_core` ou le SDK Flutter) n'ont pas eu besoin d'être réécrits — c'était le test
d'éligibilité. Aucune autre référence n'existe (fichiers d'entrée de test, jamais importés).

## Ce qui n'a PAS été déplacé et pourquoi

- **`lib/` : 0 fichier extrait.** Aucun fichier commun n'est byte-identique. Les 20
  quasi-doublons « import-only » dépendent tous du câblage DI applicatif
  (`core/providers/core_providers.dart` ou providers locaux branchés sur des repositories
  divergents). Les 30 fichiers divergents portent de vraies différences produit
  (refonte visuelle GlassCard côté manager, clés l10n `accessDeniedBodyHr` vs
  `accessDeniedBody`, `maxRetriesOverride` déjà appliqué côté HR, `personalEmail` côté
  manager, classes `Hr*`/`Manager*SmartAttendanceRepository`, garde #3406 dans
  `attendance_repository.dart`, BOM UTF-8 dans `home_screen.dart` manager, `getProgress()`
  onboarding manager-only…). Déplacer l'un d'eux sans décision produit = régression.
- **`test/` : 8 quasi-doublons « import-only »** (ils importent leur package app) et 4
  divergents (dont `repositories/repository_contract_test.dart` — contrat onboarding lié au
  PR #2663 en cours, **hors périmètre #2601**) restent dans les apps.
- **22 fichiers byte-identiques hors tests** (config, polices, mock assets, `pubspec.lock`)
  : infrastructure par package, non déplaçables (voir inventaire § « NON déplaçables »).

## melos.yaml — aucune modification requise

- `leopardo_core` est déjà un package melos (`packages:` listé) avec un dossier `test/` —
  le script `melos run test` (`flutter test`) l'exécute déjà.
- Les 10 tests extraits n'utilisent que `flutter_test` (+ `flutter_localizations` pour le
  test welcome), déjà présents dans `leopardo_core/pubspec.yaml` (dev_dependencies /
  dependencies).
- Aucune dépendance package n'a changé entre apps et core.

## Vérifications (exécutées, sans SDK Dart)

1. **Intégrité** : SHA-256 identique HR@HEAD == MGR@HEAD == `leopardo_core/test/` pour les 10 fichiers.
2. **Grep** : zéro référence aux anciens chemins (hors docs historiques `docs/JOURNAL_RACINE.md`,
   `docs/PROMPTS_EXECUTION/...` qui décrivent l'ancien monolithe `front/mobile/`).
3. **Grep** : aucun `import ... *_test.dart` dans le code applicatif des 3 packages.
4. **Grep** : les tests restants de HR/Manager n'importent pas les fichiers déplacés.
5. **CI** : `mobile-apps-ci.yml` lance `flutter test` par package — HR/Manager conservent
   12 tests chacun, `leopardo_core` en a 19 → rien ne devient vide, rien ne casse.
6. **Garde-fou split** : `validate-mobile-apps-split.ps1` interdit aux imports de
   `leopardo_core/lib` de référencer un package app — les tests extraits n'importent que
   core/Flutter, et ils ne vivent pas sous `lib/` → garde verte.
7. **Lint** : `analysis_options.yaml` strictement identique entre apps et core → mêmes lints.

## Risques restants

| Risque | Gravité | Mitigation |
|---|---|---|
| Duplication réelle des écrans `lib/` toujours en place (l'objectif du titre #2601 n'est pas atteint en un lot) | Moyenne | Lot suivant décrit ci-dessous ; l'inventaire documente chaque fichier |
| `flutter analyze`/`flutter test` non exécutés (pas de SDK Dart dans le sandbox) | Faible | CI `mobile-apps-ci.yml` (analyze + test) à confirmer à l'ouverture de la PR |
| Suite de tests core plus lourde (19 fichiers) | Négligeable | Tests unitaires/widgets courts |
| Risque de régression Riverpod si le lot suivant déplace les providers sans préserver l'identité de `apiClientProvider` (onUnauthorized #2737) | Élevée (lot suivant) | Prérequis documentés — ne pas déplacer la DI avant unification des repositories |
| `leopardo_core/assets/mock/` non comparé aux apps (potentiel doublon d'assets) | Faible | Audit séparé (hors périmètre écrans) |

## Lot suivant (recommandations, ordonnées)

1. **Unifier dans `leopardo_core` les repositories locaux divergents** (`auth_repository`,
   `attendance_repository`, `settings_repository`, `user_auth_repository`,
   `onboarding_repository`) en paramétrant les différences (email perso, retries, gardes) —
   c'est le prérequis de tout le reste.
2. **Extraire les providers de repositories identiques** de `core_providers.dart` vers un
   fichier core (`feature_repository_providers.dart`) avec `export` depuis les apps —
   en préservant l'identité du provider `apiClientProvider` câblé `onUnauthorized` (#2737).
3. **Déplacer les 20 quasi-doublons `lib/`** (réécriture des imports app → core), par feature,
   en commençant par les providers « feuille » (absences, cabinet, company_branding,
   evaluations, notifications, payrolls, salary_advances, schedules, tasks, team) puis les
   écrans qui n'utilisent que ces providers.
4. **Déplacer les 8 tests « import-only »** une fois leurs cibles déplacées.
5. **Trancher les 30 fichiers divergents** (GlassCard manager vs HR, l10n, comportements) :
   décision produit avant unification ; pour les écrans « morts » (ex.
   `company_request_screen` #2763) suppression plutôt que migration.

## Statut

- [x] Inventaire complet (`DEDUP_T008_INVENTAIRE.md`)
- [x] Lot 1 : 10 tests byte-identiques extraits vers `leopardo_core`
- [x] Vérifications grep + intégrité
- [x] CHANGELOG [Unreleased]
- [ ] Lot 2 : repositories partagés unifiés (prérequis DI) — **nouvelle issue recommandée**
- [ ] Lot 3 : déplacement des 20 quasi-doublons `lib/` — **nouvelle issue recommandée**
