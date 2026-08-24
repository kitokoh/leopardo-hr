# Spec — #5279 Dé-duplication mobile (suite #2601) — Lot 1

**Statut** : implémentation · **Branche** : `mod/platform/5279-mobile-dedup` · **Date** : 2026-08-23

## Contexte

Suite du chantier #2601 : les écrans/providers partagés entre `leopardo_hr` et
`leopardo_manager` (100 % identiques hors préfixe d'import) doivent vivre dans
`leopardo_core`, les apps les consommant depuis le core.

Audit 2026-08-23 : 49 candidats ≥ 60 % similaires, dont **20 paires 100 %
identiques** (ne diffèrent que par `package:leopardo_hr/` vs
`package:leopardo_manager/`). Les repositories de 10 features sont déjà dans le
core (#2601) — les providers/écrans correspondants sont les doublons résiduels.

## Périmètre du lot 1 (10 fichiers × 2 apps)

Providers extraits vers `leopardo_core/lib/features/<feat>/providers/` :
absences, cabinet, company_branding, evaluations, notifications, payrolls,
salary_advances, schedules.

Écrans extraits vers `leopardo_core/lib/features/<feat>/screens/` :
`cabinet_screen.dart`, `notification_list_screen.dart`.

## Dépendance bloquante : `core_providers.dart` (apps)

Tous les fichiers cibles importent `package:<app>/core/providers/core_providers.dart`.
Ce fichier n'est pas extractible tel quel (référence des repositories spécifiques
aux apps : auth, attendance, settings, user_auth, onboarding + contracts pour hr /
ai_chat, vehicle_position, approvals pour manager).

**Solution** : création de `leopardo_core/lib/core/providers/core_providers.dart`
avec la partie commune (services core + repositoryProviders des features dans le
core). Les apps conservent leur `core/providers/core_providers.dart` réduit à
leurs providers spécifiques + `export` du core — les imports existants
`package:<app>/core/providers/core_providers.dart` restent valides.

## Également (pré-requis build)

Conflit dependabot non résolu sur main : `leopardo_core` exige
`flutter_secure_storage ^11` mais les apps sont en `^10.1` → tout `flutter pub
get` échoue. Fix : aligner `leopardo_hr`/`leopardo_manager` sur `^11.0.0`
(API compatibles, vérifié par `flutter analyze`). Ajout de `image_picker` au
pubspec du core (utilisé par `cabinet_screen.dart` extrait).

## DoD du lot 1

- [x] 10 paires de fichiers identiques éliminées (20 instances en moins dans les apps)
- [x] `flutter analyze` 0 erreur sur leopardo_core (hors l10n préexistante),
  leopardo_hr, leopardo_manager — 0 NOUVELLE erreur introduite
- [x] `dart format` appliqué
- [ ] PR avec `Closes #5279` — l'issue reste ouverte (DoD global ≥ 50 % non atteint ; lots suivants : auth, attendance, team, tasks, user_auth, settings, onboarding)

## Hors périmètre lot 1

- L'extraction de `core_providers` app→core complet (nécessite auth/attendance/settings/user_auth repositories dans le core)
- Les grappes dépendantes de `auth_provider` (attendance history, register_screen, tenant_branding)
- Le DoD global ≥ 50 % (lots 2+)
