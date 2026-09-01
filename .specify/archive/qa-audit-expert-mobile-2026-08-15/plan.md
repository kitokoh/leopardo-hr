# Plan: Audit expert Mobile — 2026-08-15

**Input**: spec.md (US M1-M9 + T001-T004) + Constitution + audit 2026-08-15

## Architecture / Décisions techniques

- **M1 Onboarding** : changement **mobile d'abord** (le backend PATCH + `step_key` est l'API canonique) :
  - `OnboardingStep` (leopardo_core) : ajouter `stepKey` (parse de `step_key`).
  - `OnboardingRepository` (employee/manager/hr) : `PATCH /onboarding-setup/{stepKey}/complete|skip`.
  - Corriger le test de contrat `leopardo_hr/test/repositories/repository_contract_test.dart` (PATCH + step_key).
- **M2 Cabinet manager** : renommer la route manager en `/cabinet/folder/:folderId` (alignement employee/HR) — `app.dart:179` inchangé.
- **M3 Session** : `checkAuth()` ne supprime le token que sur 401 ; brancher `onUnauthorized` → `authProvider.logout()` dans les 3 apps (`apiClientProvider`).
- **M4 Double auth** : clés SecureStorage distinctes (`auth_token_employee` / `auth_token_user`) avec migration de lecture (ancienne clé en fallback).
- **M5 i18n/mojibake** : ré-encoder les fichiers concernés en UTF-8 ; router les chaînes dures via `context.l10n` (échantillon prioritaire : attendance, welcome, onboarding, payroll, expenses, evaluations) ; labels mixtes → clés.
- **M6 Monnaie** : dériver la devise du payload (`employee.currency`, summary) avec fallback pays du tenant ; `NumberFormat.currency(locale, symbol)` partout ; `detail_schema`/`list_schema` acceptent devise/locale en paramètre.
- **M7 Idempotence & erreurs** : `maxRetriesOverride: 0` sur `POST /salary-advances` (et l'appel IA) ; interprétation du 403 selon payload (`suspended`) ; offline detection standardisée (types DioException dans le core, partagée par employee/manager).
- **M8 Android** : `android:foregroundServiceType="location"` + permission `FOREGROUND_SERVICE_LOCATION` (employee) ; `usesCleartextTraffic` dans le manifest **debug** seulement ; `Locale` de platform_admin résolue depuis préférences (comme les autres apps).
- **M9 Hygiène** : supprimer l'écran mort `company_request_screen` ; `String.fromEnvironment` pour le client ID Google + masquer le bouton démo en release ; `tracesSampleRate: 0.2` + exclusions PII ; `tryParse` pour `requested_check_in` ; retirer les méthodes mortes (ou les router via `/me/*`) ; finir ou retirer le stub marketing (T002).

## Phases

### Phase 1 — P1 (M1, M2 + T001-T004)
- Onboarding PATCH + step_key (3 apps + contrat) ; route cabinet manager ; T001 (départements hierarchy), T003 (expense catch), T004 (AI voice).

### Phase 2 — P2 (M3-M8)
- Session (token 401-only + onUnauthorized), double auth (clés), mojibake + i18n, monnaie/formatage, idempotence/403/offline, Android manifest + locale platform_admin.

### Phase 3 — P3 (M9)
- Écran mort, client ID/creds, Sentry sampling, tryParse, méthodes mortes, T002 (stats marketing réelles).

## Fichiers touchés (référence)

- `front/mobile_apps/leopardo_core/lib/models/onboarding_step.dart`, `core/api/api_client.dart`, `core/storage/secure_storage.dart`, `core/models/{detail_schema,list_schema}.dart`
- `front/mobile_apps/leopardo_{employee,manager,hr}/lib/features/onboarding/data/onboarding_repository.dart`
- `front/mobile_apps/leopardo_hr/test/repositories/repository_contract_test.dart`
- `front/mobile_apps/leopardo_manager/lib/app.dart` (+ routeurs cabinet des 3 apps)
- `front/mobile_apps/leopardo_employee/lib/features/auth/data/auth_repository.dart`, `user_auth/data/user_auth_repository.dart`
- `front/mobile_apps/leopardo_{employee,manager,hr}/lib/features/{evaluations,expenses,personal_space,smart_attendance,attendance,payrolls,salary_advances,welcome,onboarding}/**`
- `front/mobile_apps/leopardo_employee/android/app/src/{main,debug}/AndroidManifest.xml`
- `front/mobile_apps/leopardo_platform_admin/lib/src/platform_admin_app.dart`
- `front/mobile_apps/leopardo_marketing/lib/features/marketing/screens/stats_dashboard_screen.dart`

## Contraintes

- `leopardo_core` : toute modification partagée va dans le core (règle README mobile).
- Garder les garde-fous CI : `validate-mobile-apps-split.ps1`, `validate-mobile-workflow-contracts.ps1`, `check-mobile-manifest-routes.sh` verts.
- Pas de Dart SDK dans l'environnement de test → vérification par lecture + contrats ; `flutter analyze` à passer en CI.
- Rétro-compat : ne pas casser `/me/trainings`, `getChecklist()`, la lecture des anciens tokens (fallback).
