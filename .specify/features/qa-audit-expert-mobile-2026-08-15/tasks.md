# Tasks: Audit expert Mobile — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

> Conversion en issues GitHub : label `qa-audit-2026-08-15`, méthode Spec Kit taskstoissues.
> **Sessions précédentes (canoniques, à ne pas dupliquer)** : série mobile #2594-#2601 (T001 departments hierarchy, T002 stats marketing, T003 expense catch, T004 AI voice, T005 base URLs onrender, T006 password123 core, T007 offline drift, T008 hr/manager dedup) + #2631 onboarding PATCH (PR #2663 en cours). Mon T079 (doublon) fermé en faveur de #2631.

## Tâches des sessions précédentes (issues #2594-#2601, #2631)

- [ ] T001 [P3] `GET /departments/{department}/hierarchy` (issue #2594)
- [ ] T002 [P3] Stats marketing réelles (issue #2595)
- [ ] T003 [P3] Expense submit catch (issue #2596)
- [ ] T004 [P3] AI Voice placeholders (issue #2597)
- [ ] T005 [P3] URLs de base onrender (issue #2598)
- [ ] T006 [P3] password123 démo core (issue #2599)
- [ ] T007 [P3] Offline drift (issue #2600)
- [ ] T008 [P3] Dé-duplication hr/manager (issue #2601)
- [ ] T009 [P1] Onboarding PATCH + stepKey (issue #2631 — PR #2663 en cours)

## Phase 1 — P1 (US M2)

- [x] T079 [P1] M1 Onboarding — **doublon fermé** : canonique #2631 (PR #2663 en cours). (issue #2734)
- [x] T080 [P1] M2 Cabinet manager : route `/cabinet/folder/:folderId` (alignement employee/HR) — fini le no-match GoRouter. (issue #2735)

## Phase 2 — P2 (US M3-M8)

- [x] T081 [P2] M3 `checkAuth()` : ne supprimer le token que sur 401 (les erreurs réseau conservent la session). (issue #2736)
- [x] T082 [P2] M3 Brancher `onUnauthorized` → logout local dans les 3 apps (fini l'UI authentifiée fantôme). (issue #2737)
- [x] T083 [P2] M5 Ré-encoder les fichiers mojibake (evaluations, expenses, personal_space, smart_attendance + copies manager/hr). (issue #2738)
- [x] T084 [P2] M4 Double auth : clés SecureStorage distinctes (`auth_token_employee`/`auth_token_user`) + migration lecture. (issue #2739)
- [ ] T085 [P2] M5 i18n : router les chaînes codées en dur via `context.l10n` (échantillon prioritaire des écrans principaux). (issue #2740)
- [ ] T086 [P2] M6 Devise : dériver du payload tenant (fini `DZD` codé en dur) + `NumberFormat.currency(locale)`. (issue #2741)
- [x] T087 [P2] M7 `POST /salary-advances` + appel IA : `maxRetriesOverride: 0` (fini les doublons). (issue #2742)
- [x] T088 [P2] M7 403 : différencier `suspended` (payload) du défaut de permission ; message correct. (issue #2743)
- [ ] T089 [P2] M6/M7 Formatage nombres + `detail_schema`/`list_schema` (devise/locale en paramètre, fini €/Oui/Non). (issue #2744)
- [ ] T090 [P2] M7 Offline manager : même détection que l'app employé (types DioException). (issue #2745)
- [x] T091 [P2] M8 `Locale` platform_admin résolue depuis les préférences (fini `Locale('fr')` codé). (issue #2761)
- [x] T092 [P2] M8 Android : `foregroundServiceType="location"` + `FOREGROUND_SERVICE_LOCATION` (employee) ; `cleartextTraffic` debug seulement. (issue #2762)

## Phase 3 — P3 (US M9)

- [x] T093 [P3] M9 Supprimer `company_request_screen.dart` (écran mort, 422 garanti). (issue #2763)
- [ ] T094 [P3] M9 `detail_schema`/`list_schema` : devise/locale en paramètre (code mort €). (issue #2764)
- [ ] T095 [P3] M9 `main.dart` : Google client ID via `String.fromEnvironment` + masquer le bouton démo en release. (issue #2765)
- [x] T096 [P3] M9 Sentry : `tracesSampleRate` borné (0.2) + exclusions PII. (issue #2766)
- [x] T097 [P3] M9 `DateTime.parse(requested_check_in)` → `tryParse` null-safe. (issue #2767)
- [x] T098 [P3] M9 Méthodes mortes payroll/`getDailySummary` : retirer ou router via `/me/*`. (issue #2769)

## Convergence

- [ ] T099 Mettre à jour `.specify/memory/project-state.md`, `CHANGELOG.md`, `AGENTS.md`, cocher les tâches après merge.
