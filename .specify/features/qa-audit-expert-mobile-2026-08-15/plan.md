# Plan: Audit Expert Mobile — Applications Flutter — 2026-08-15

**Input**: spec.md (US1-US3) + Constitution + registre project-state

## Architecture / Décisions techniques

### US1 — Endpoint organigramme (P1) — IMPLÉMENTABLE (PHP)
- Ajouter `GET /departments/{department}/hierarchy` dans `api/routes/modules/rh.php` (groupe tenant, `api.manager` requis comme les autres routes org) → nouvelle méthode `hierarchy()` sur `DepartmentController` (ou controller dédié) : charge le département scopé, ses équipes, managers et employés actifs, retourne un arbre `{department, teams[], managers[], employees[]}`.
- Modèle : `Department` → relation `manager`, `employees` existante (vérifier relations dans `app/Modules/HR/Domain/Models/Department.php`).
- Test : `tests/Feature/OrganigrammeTest` — 200 arbre, 404 cross-tenant, 404 département inexistant, 200 arbre vide.

### US2 — Stats marketing réelles (P2) — DOCUMENTÉE (Dart, pas de toolchain)
- Préconisation : `MarketingRepository.fetchStats()` — agrégation des posts (`/marketing/posts` + `/marketing/posts/{id}/publish`) par plateforme/période ; retirer les littéraux `24_310` etc. (`stats_dashboard_screen.dart:39-54`) ; états d'erreur AsyncValue. À implémenter en CI/session avec Flutter.

### US3 — Hygiène mobile (P3) — DOCUMENTÉE (Dart)
- **Expense catch** : `try { await … } finally { loading=false }` → ajouter `catch` + message (`leopardo_employee|manager|hr/.../expense_list_screen.dart:38`).
- **AI Voice** : retirer les écrans placeholder (`ai_voice_screen.dart:6-48` ×3 apps) + routes GoRouter associées OU câbler `/ai/voice/transcribe` (issue #2213).
- **URLs** : `api_client.dart:13-16` (défaut `gestionemployerbackend.onrender.com` → variable de build `--dart-define=API_BASE_URL` obligatoire en release, fallback dédié) ; `core_providers.dart:85` (retirer le fallback `http://leopardo.local:7878`).
- **password123** : `demo_user_bottom_sheet.dart:13` — gate `kDebugMode`/`DEMO_MODE_ENABLED`.
- **Offline dual** : décision — **drift** (`AttendanceOfflineService`) devient l'unique propriétaire des pointages offline ; le Hive legacy (`offline_punches`, `OfflineSyncService`) est retiré après migration (documenté, chantier séparé).
- **Dé-duplication** : `leopardo_hr` ≈ `leopardo_manager` (92 vs 93 fichiers) → extraire les screens partagés vers `leopardo_core` (chantier structurel documenté).

## Phases

### Phase 1 — US1 endpoint backend (P1)
- T001 Route + controller `hierarchy` + tests (200/404/arbre vide)

### Phase 2 — US2+US3 documentation & issues (P2/P3)
- T002 Stats marketing réelles (instructions Dart + issue)
- T003 Expense catch (3 apps)
- T004 AI Voice placeholders
- T005 URLs de base configurables
- T006 password123 hors release
- T007 Offline : un seul mécanisme
- T008 Dé-duplication leopardo_hr/manager

## Validation finale
`php artisan test --filter=OrganigrammeTest` + phpstan/pint ; les tasks Dart sont vérifiées par `flutter analyze` en CI (issue dédiée) — non poussées depuis l'environnement d'audit sans validation.
