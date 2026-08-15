# Tasks: Audit Expert Mobile — Applications Flutter — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — US1 Endpoint organigramme (P1) — issue #2594

- [x] T001 [US1] `GET /departments/{department}/hierarchy` : route (`api/routes/modules/rh.php`, groupe tenant + `api.manager`) + méthode `hierarchy()` sur `DepartmentController` (arbre department/teams/managers/employees, scopé `company_id`) + `tests/Feature/OrganigrammeTest` (200 arbre, 404 cross-tenant, 404 inexistant, 200 arbre vide) — corrige le 404 mobile `organigramme_repository.dart:61`

## Phase 2 — US2+US3 Hygiène mobile (P2/P3) — issues #2595-#2601 (documentation + implémentation Dart à valider en CI)

- [ ] T002 [P] [US2] Stats marketing réelles : retirer les littéraux fake (`leopardo_marketing/lib/features/marketing/screens/stats_dashboard_screen.dart:39-54`), ajouter `fetchStats()` au `MarketingRepository` (agrégation `/marketing/posts`), états d'erreur AsyncValue — implémentation Dart à valider `flutter analyze`
- [ ] T003 [P] [US3] Expense submit : ajouter `catch` + retour visuel au `try/finally` sans catch (`leopardo_employee|manager|hr/lib/features/expenses/screens/expense_list_screen.dart:38`)
- [ ] T004 [P] [US3] AI Voice : retirer les écrans placeholder « Bientôt disponible » (`ai_voice_screen.dart:6-48` ×3 apps) + routes GoRouter, OU câbler `/ai/voice/transcribe` (issue #2213)
- [ ] T005 [P] [US3] URLs de base : remplacer le défaut `gestionemployerbackend.onrender.com` (`leopardo_core/lib/core/api/api_client.dart:13-16`) par `--dart-define=API_BASE_URL` obligatoire en release ; retirer le fallback `http://leopardo.local:7878` (`core_providers.dart:85`, `settings_screen.dart:1121`)
- [ ] T006 [P] [US3] `password123` démo hors release (`leopardo_core/lib/core/widgets/demo_user_bottom_sheet.dart:13`) — gate `kDebugMode`/flag démo
- [ ] T007 [P] [US3] Offline : décision drift (`AttendanceOfflineService`) comme propriétaire unique ; Hive legacy (`offline_punches`, `OfflineSyncService`) retiré après migration — chantier documenté
- [ ] T008 [P] [US3] Dé-duplication `leopardo_hr`/`leopardo_manager` (92 vs 93 fichiers) : extraction des écrans partagés vers `leopardo_core` — chantier structurel documenté

## Dependencies & Execution Order

- Phase 1 (backend PHP) implémentable + testable ici ; Phase 2 tasks Dart **documentées** (pas de toolchain Flutter) — chaque task porte des instructions d'implémentation et sera validée par `flutter analyze` en CI.
- PR : `fix/qa-<n>-organigramme-hierarchy` (T001) avec `Closes #<issue>` ; les tasks T002-T008 restent ouvertes avec issues dédiées.
