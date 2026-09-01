# Feature Specification: QA Expert #2 — Mobile (front/mobile_apps) (2026-08-15)

**Feature**: `qa-expert2-mobile-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + revue statique experte (rg/scripts) + cross-check issues existantes.

## Contexte

Deuxième vague de test expert de la mission propriétaire (tester « dans tous les sens », consigner chaque manquement selon la méthode Spec Kit, puis implémenter). Les findings ci-dessous sont **nouveaux** : vérifiés contre les ~140 issues ouvertes et les branches/PRs existantes (règle anti-doublon #2400).

## Findings non couverts (issues créées)

### #3047 [P2] Mobile — notifications « marquer lu/lues » : PUT au lieu de POST/PATCH → 405 garanti (employee, manager, hr)

> **Constat** : Les repos mobiles marquent les notifications comme lues avec `PUT` alors que le backend n'expose que `POST /notifications/read-all` et `PATCH /notifications/{notification}/read` → **405 garanti**.
> **Preuve** : - `front/mobile_apps/leopardo_employee/lib/features/notifications/data/notification_repository.dart:24,32` (`method: 'PUT'`)
- `front/mobile_apps/leopardo_manager/lib/features/modules/data/modules_repository.dart:272,281` (idem)
- Backend : `api/routes/modules/rh.php:175-176`, `api/routes/modules/dashboard.php:34-35`
> **Impact** : Toute action « tout marquer lu » ou « marquer lu » échoue en 405 sur les 3 apps tenant.

### #3048 [P2] Mobile — POST /user/company-requests sans maxRetriesOverride → demande doublée sur timeout (classe #2742 non couverte)

> **Constat** : `POST /user/company-requests` hérite des retries automatiques → si le serveur a réussi mais le client timeout, la demande de rattachement est créée en double.
> **Preuve** : - `front/mobile_apps/leopardo_{employee,manager,hr}/lib/features/user_auth/data/user_auth_repository.dart:141` (pas de `maxRetriesOverride: 0`)
> **Impact** : Doublons de company_requests (classe #2742 appliquée aux avances mais pas ici).

### #3049 [P3] Mobile manager — GoRoute /cabinet/folder/:folderId déclarée 2× (résidu fix #2748)

> **Constat** : La route `/cabinet/folder/:folderId` est déclarée deux fois dans le GoRouter du manager.
> **Preuve** : - `front/mobile_apps/leopardo_manager/lib/app.dart:209,220`
> **Impact** : Hygiène router (résidu #2748).

### #3050 [P3] Mobile — écran mort PersonalSpaceScreen (« Créer mon entreprise » inaccessible) dans employee/manager/hr

> **Constat** : `PersonalSpaceScreen` propose « Créer mon entreprise » mais n'est atteignable depuis aucune navigation des 3 apps.
> **Preuve** : - `front/mobile_apps/leopardo_{employee,manager,hr}/lib/features/personal_space/screens/personal_space_screen.dart:8`
> **Impact** : Écran mort annonçant une capacité inexistante.

### #3051 [P3] Mobile — leopardo_hr sans ShellRoute/bottom-nav (employee/manager en ont une)

> **Constat** : L'app `leopardo_hr` ne définit aucune `ShellRoute` (bottom navigation), contrairement à employee et manager — navigation principale absente.
> **Preuve** : - `front/mobile_apps/leopardo_hr/lib/app.dart:118+` (0 ShellRoute)
> **Impact** : Expérience RH sans navigation par onglets (incohérence entre apps).

### #3052 [P3] Mobile — cast direct data['data']['id'] as String alors que le backend renvoie un int (AttendanceLogResource)

> **Constat** : `attendance_offline_service.dart` caste `data['data']['id'] as String` mais `AttendanceLogResource` renvoie l'id en **int** → TypeError latent.
> **Preuve** : - `front/mobile_apps/leopardo_core/lib/offline/services/attendance_offline_service.dart:65`
- `api/app/.../AttendanceLogResource.php:41` (int)
> **Impact** : Crash possible sur certaines données de pointage offline.

### #3053 [P3] Mobile — ThemeMode.dark forcé dans les 5 apps → lightTheme mort

> **Constat** : Les 5 apps forcent `ThemeMode.dark`, rendant les thèmes clairs morts (aucune préférence utilisateur).
> **Preuve** : - employee:313, manager:367, hr:344, platform_admin:97, marketing:126 (main.dart)
> **Impact** : Hygiène/thème non configurable.

### #3054 [P3] Mobile — DateTime.parse non gardés résiduels : hr attendance_repository:552 + cabinet/monthly_summary/absence/geo-sessions

> **Constat** : Des `DateTime.parse` sans garde subsistent (fix #2767 incomplet) : hr:552, cabinet_folder:31, cabinet_document:31, monthly_summary:60-61,103, absence:56-57, geo_attendance_session:48-56.
> **Preuve** : - `front/mobile_apps/leopardo_hr/lib/features/attendance/data/attendance_repository.dart:552`
- cabinet/…, monthly_summary/…, absence/…, geo_attendance_session/…
> **Impact** : Crash sur données malformées.

## Règles d'implémentation
- Une PR par issue avec `Closes #N` dans le body (Constitution §VII).
- Pas de données fabriquées : endpoint réel ou état vide honnête.
- i18n : les 4 locales FR/EN/TR/AR dans le même changement ; jamais de clés brutes affichées.
- Vérifier la garde anti-doublon avant push : `git ls-remote --heads origin | grep <issue>`.