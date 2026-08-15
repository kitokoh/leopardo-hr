# Audit statique mobile Flutter — Leopardo HR (2026-08-15)

**Périmètre vérifié** : `front/mobile_apps/` — leopardo_employee, leopardo_manager, leopardo_hr,
leopardo_marketing, leopardo_platform_admin + package partagé `leopardo_core`.
Commit HEAD : `0285edc7` (merge PR #3185). Aucun fichier modifié (audit read-only).

**Note périmètre** : l'app `leopardo_kiosk` **n'existe pas** en Flutter. Le seul kiosk du repo est
`front/zkteco-kiosk` (app JS/PHP) + `.github/workflows/kiosk-ci.yml`. Les constats ci-dessous couvrent
donc 5 apps Flutter + `leopardo_core`.

Légende : 🔴 P1 (casse build/analyse, crash ou fonctionnalité majeure cassée) · 🟠 P2 · 🟡 P3.
Chaque constat a été vérifié dans le code à HEAD ; les affirmations de l'énoncé qui ne se
reproduisent plus à HEAD sont signalées explicitement en fin de document.

---

## 1. Routes GoRouter — navigation cassée et routes mortes

### C1 🔴 P1 — Manager : navigation vers des routes jamais enregistrées (`/team`, `/tasks`, `/me/monthly`) → écran d'erreur GoRouter
Le routeur manager (`leopardo_manager/lib/app.dart:128-244`) n'enregistre **ni `/team`, ni `/tasks`, ni `/me/monthly`**, alors que 3 navigations y poussent :
- `leopardo_manager/lib/features/home/screens/manager_main_shell.dart:29` — bottom-nav « Équipe » → `route: '/team'`
- `leopardo_manager/lib/features/home/screens/home_screen.dart:621` — `context.push('/team')`
- `leopardo_manager/lib/features/home/screens/home_screen.dart:613` — `context.push('/tasks')`
- `leopardo_manager/lib/features/team/screens/team_screen.dart:410` — `context.push('/tasks')`
- `leopardo_manager/lib/features/attendance/screens/attendance_screen.dart:290` — `context.push('/me/monthly')`

Chaque tap tombe sur l'`errorBuilder` « La page demandée est introuvable » (`app.dart:49-64`).
Preuve complémentaire : 10 écrans sont importés dans `app.dart` mais **jamais routés** (0 occurrence
hors import) : `AttendanceScreen`, `HistoryScreen`, `MonthlySummaryScreen`, `AbsenceListScreen`,
`SalaryAdvanceListScreen`, `PayrollListScreen`, `NotificationListScreen`, `EvaluationListScreen`,
`TeamScreen`, `TaskListScreen` (imports `leopardo_manager/lib/app.dart:20-21,26-29,33-35`). Ces routes
ont manifestement été perdues dans un refactor.

### C2 🟠 P2 — HR : 5 routes GoRouter mortes (enregistrées, jamais naviguées)
Dans `leopardo_hr/lib/app.dart`, aucune navigation de l'app HR ne pointe vers :
- `/approvals` (app.dart:254) — pas dans `hr_main_shell.dart:23-47` (routes: /, /attendance, /absences, /team, /settings), aucun `push` dans `lib/`
- `/onboarding` (app.dart:258) — aucun `push` (seule l'app employee y navigue, `leopardo_employee/lib/features/home/screens/home_screen.dart:355`)
- `/organigramme` (app.dart:262) — aucun `push`
- `/manager/anomalies` (app.dart:278) — aucun `push` côté HR (vivant dans manager : `leopardo_manager/.../home_screen.dart:585`)
- `/manager/corrections` (app.dart:282) — aucun `push` côté HR (vivant dans manager : `home_screen.dart:593,666`)

### C3 🟡 P3 — Manager : `/onboarding` et `/organigramme` mortes aussi
- `/onboarding` : `leopardo_manager/lib/app.dart:207` — aucun `push('/onboarding')` dans l'app manager
- `/organigramme` : `leopardo_manager/lib/app.dart:211` — aucun `push`

### C4 🟡 P3 — Deep-linking absent : aucun intent-filter VIEW/BROWSABLE dans les 4 apps Android
Les 4 `AndroidManifest.xml` n'ont que l'intent-filter MAIN/LAUNCHER, ex.
`leopardo_manager/android/app/src/main/AndroidManifest.xml:43-46` (idem employee:51-54, hr,
platform_admin). Les routes paramétrées `/cabinet/folder/:folderId` (commentées « deep-link »,
T121, employee app.dart:184-195) ne sont donc **jamais ouvrables par une URL externe** ; iOS sans
associated domains non plus. Le fallback « folderId non numérique → écran vide » (employee
app.dart:188-191) reste le seul chemin.

---

## 2. Appels API — cohérence avec `api/routes/`

### C5 🔴 P1 — App Marketing : **aucune authentification** → 401 systématique sur toutes les API
- `leopardo_marketing/lib/main.dart:25-40` : `StartupGate` avec `criticalInitializer: () async {}` (vide), aucun login, aucun token.
- `leopardo_marketing/lib/features/marketing/repositories/providers.dart:6` : seul `apiClientProvider` (core), qui lit `SecureStorage` sans jamais y écrire de token.
- Côté backend, toutes les routes marketing sont protégées `auth:sanctum` + `api.manager:marketing,principal` : `api/routes/modules/marketing.php:21`.
- Résultat : `GET /marketing/posts` (`social_post_repository.dart:12`), `POST /marketing/posts` (:14) et `POST /marketing/posts/{id}/publish` (:20) renvoient **401**. Le 401 est avalé silencieusement (`onUnauthorized` est null dans `leopardo_core/lib/core/providers/base_providers.dart:17-22` ; interceptor `api_client.dart:74-89`), l'app affiche des états vides/erreurs sans jamais pouvoir s'authentifier.
- Le README de l'app est un template `flutter create` non édité (`leopardo_marketing/README.md:3` « A new Flutter project ») — corroborre le caractère inachevé.

### C6 🟠 P2 — Retry automatique sur POST non-idempotents → doublons
`ApiClient.requestWithRetry` (`leopardo_core/lib/core/api/api_client.dart:120-185`) rejoue **toute** requête sur 502/503/504/timeout/réseau, quel que soit le verbe HTTP, avec backoff 3-6 s (`_backoff`, api_client.dart:198-200). Trois POST non-idempotents héritent des retries par défaut :
- **Publish marketing** : `leopardo_marketing/lib/features/marketing/repositories/social_post_repository.dart:20` — `POST /marketing/posts/$postId/publish` sans `maxRetriesOverride` → 2 retries. Le contrôleur backend exécute `schedulePost->execute()` sans garde d'idempotence (`api/app/Modules/Marketing/Interfaces/Api/V1/Controllers/SocialPostController.php:101-108`) → double publication/planification possible.
- **CreatePost** : `social_post_repository.dart:14` — idem (doublon de post).
- **zone_enter / zone_exit** : `leopardo_employee/lib/features/smart_attendance/data/smart_attendance_repository.dart:45-60` — `POST /smart-attendance/geo-events` avec `maxRetriesOverride: 1` → un timeout après traitement serveur génère un 2ᵉ événement (double entrée/sortie de zone facturée).

### C7 🟠 P2 — Échec `zone_enter` avalé sans log ni file d'attente → événement perdu définitivement
`leopardo_employee/lib/features/smart_attendance/services/background_location_service.dart:204-213` :
```dart
try { await _repository.sendGeoEvent(...); } catch (_) {
  // Échec silencieux en background : sera retransmis au prochain cycle
}
```
Commentaire faux : il n'y a **aucune file/queue** — si le device reste dans la zone, aucun nouveau
cycle n'émet d'événement ; le `catch (_)` ne logge rien. Pointage d'entrée silencieusement perdu.

### C8 🟡 P3 — URLs par défaut hardcodées (dev/Edge)
- `leopardo_employee/lib/core/providers/core_providers.dart:91` : fallback Edge `'http://leopardo.local:7878'` (hôte mDNS non résoluble hors LAN Edge) ; hint de saisie idem `leopardo_employee/lib/features/settings/screens/settings_screen.dart:1121`.
- `leopardo_core/lib/core/api/api_client.dart:15-17` : `http://10.0.2.2:8000/api/v1` (émulateur Android) et `http://127.0.0.1:8000/api/v1` en debug uniquement (protégé par `USE_LOCAL_API` + `kDebugMode`, api_client.dart:120-139) — acceptable, mais c'est le 2ᵉ endroit où la stack d'URL est dupliquée.

### C9 🟡 P3 — Vérifié conforme (constats d'énoncé non reproductibles)
- `approve`/`reject` : `POST /approvals/{id}/approve|reject` avec `maxRetriesOverride: 0` (leopardo_hr + leopardo_manager `approval_repository.dart:24,34`) → **pas** de risque doublon ; méthode POST = conforme backend `api/routes/modules/hr_extended.php:56-57`.
- `PUT /smart-attendance/preferences` (smart_attendance_repository.dart:64) = conforme `api/app/Modules/SmartAttendance/routes/smart_attendance.php:31` (`Route::put('/preferences')`).
- `POST /marketing/posts` (alias) = conforme `api/routes/modules/marketing.php:55-56` (alias #1435).
- `/attendance/{logId}` PUT, `/attendance/corrections` POST/PUT, `/cabinet/shares` POST, `/user/*`, `/auth/language` PATCH, `/evaluations/{id}/acknowledge` PUT, `/me/company-qr/scan` POST, `/device-tokens`, `/onboarding-setup/*` PATCH : tous vérifiés présents avec la bonne méthode côté backend (cross-check `api/routes/`).

---

## 3. Clés FCM placeholder commitées

### C10 🔴 P1 — `google-services.json` ×4 : app_id zéroé + `package_name` copié de l'app employee
Les 4 fichiers sont commités (git-tracked) et contiennent tous :
- `"mobilesdk_app_id": "1:000000000000:android:0000000000000000000000"` (zéroé)
- `"package_name": "com.leopardo.employee"` **dans les 4 apps**, alors que les `applicationId` réels sont `com.leopardo.rh` (hr), `com.leopardo.manager`, `com.leopardo.platformadmin` (`leopardo_hr/android/app/build.gradle.kts:10,25`, idem manager:10,25, platform_admin:10,25).

Fichiers : `leopardo_{employee,hr,manager,platform_admin}/android/app/google-services.json` (ex. hr : lignes 9-13, manager : lignes 9-13). Impact : le plugin google-services ne trouve aucun client correspondant pour 3 apps → `FirebaseApp`/FCM ne s'initialise pas (crash `FirebaseOptions` manquant au démarrage ou push mort).

### C11 🔴 P1 — `GoogleService-Info.plist` ×4 : clé API littérale `REDACTED_GOOGLE_API_KEY`
Les 4 `leopardo_{employee,hr,manager,platform_admin}/ios/Runner/GoogleService-Info.plist` contiennent `<key>API_KEY</key><string>REDACTED_GOOGLE_API_KEY</string>` (ex. employee:8-9, hr:8-9, manager:8-9, platform_admin). FCM/APNs iOS ne peut pas s'initialiser avec un placeholder — push iOS mort sur les 4 apps, et `GCM_SENDER_ID`/`BUNDLE_ID` incohérents entre apps (employee vs manager).

---

## 4. Typage cassé

### C12 🔴 P1 — HR onboarding : `_complete(int stepId)` appelé avec un `String` + `completeStep(int)` → 2 erreurs de type
`leopardo_hr/lib/features/onboarding/screens/onboarding_screen.dart` :
- ligne 18 : `Future<void> _complete(int stepId) async {`
- ligne 20 : `await ref.read(onboardingRepositoryProvider).completeStep(stepId);` — `completeStep(String)` (onboarding_repository.dart:24) reçoit un `int` → `argument_type_not_assignable`
- ligne 178 : `onPressed: () => _complete(step.key),` — `step.key` est un `String` (`leopardo_core/lib/models/onboarding_step.dart:3`) → 2ᵉ erreur

Les versions employee (`onboarding_screen.dart:20-22`) et manager (`onboarding_screen.dart:54-56`) utilisent correctement `String stepKey`. C'est exactement le cas « int vs String » : l'écran HR est la copie divergente non compilable.

### C13 🔴 P1 — `attendance_repository.dart:543-545` : deux variantes cassées (HR vs Manager)
- **HR** : `leopardo_hr/lib/features/attendance/data/attendance_repository.dart:543,545`
  ```dart
  requestedCheckIn: DateTime.parse(json['requested_check_in'].toString()),
  requestedCheckOut: json['requested_check_out'] != null
      ? DateTime.parse(json['requested_check_out'].toString()) : null,
  ```
  `json['requested_check_in']` absent → `.toString()` → `"null"` → `FormatException` **non rattrapée** → crash du chargement de la liste des corrections (ligne annoncée dans l'énoncé : 552 ; à HEAD c'est 543/545).
- **Manager** : `leopardo_manager/lib/features/attendance/data/attendance_repository.dart:543,545`
  `DateTime.tryParse(...)` renvoie `DateTime?` assigné au champ **non-nullable** `final DateTime requestedCheckIn` (modèle ligne 530) → erreur de type statique (`argument_type_not_assignable`) → l'app manager ne passe pas `flutter analyze`.
  Les deux fichiers divergent l'un de l'autre et sont chacun cassés.

### C14 🟠 P2 — `leopardo_core/lib/models/attendance_log.dart:66,75,77` — `DateTime.parse` sans garde
```dart
date: DateTime.parse((json['date'] ?? DateTime.now().toIso8601String()) as String),
checkIn: json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
checkOut: json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
```
Modèle partagé par les 3 apps principales ; une date malformée ou un `check_in` non ISO → `FormatException` → crash de la liste de pointage. Les autres `DateTime.parse` cités par l'énoncé (cabinet_folder, monthly_summary, absence, geo_attendance_session) ont été corrigés à HEAD — seul celui-ci (et HR 543) résiste.

### C15 🟡 P3 — `leopardo_marketing/lib/features/marketing/screens/editorial_calendar_screen.dart:25` — `DateTime.parse(post['scheduled_at'])`
Null-guardé (ligne 24) mais pas de `tryParse` : un `scheduled_at` au format invalide (ex. date locale « 15/08/2026 ») → `FormatException` dans le filtre du calendrier.

---

## 5. Fiabilité — échecs avalés

### C16 🟡 P3 — `SyncService` : subscription jamais cancelée + `start()` dans le corps du provider
- `leopardo_core/lib/offline/services/sync_service.dart:60-67` : `start()` fait `Connectivity().onConnectivityChanged.listen(...)` sans conserver la `StreamSubscription` ; `stop()` (lignes 69-72) cancel le timer et `close()` le `_modeController` mais **pas** la subscription → fuite + callbacks sur service arrêté → `_setMode` fait `_modeController.add(mode)` (lignes 113-118) sur un controller fermé → `Bad state` en zone async.
- Le `start()` est appelé dans le corps du provider (`leopardo_employee/lib/core/providers/core_providers.dart:98`) : toute invalidation du provider redémarre le service (side-effect de construction, pattern Riverpod déconseillé).

### C17 🟡 P3 — `catch (_)` silencieux sans log
- `leopardo_hr/lib/features/smart_attendance/screens/smart_attendance_dashboard_screen.dart:100` et `leopardo_manager/.../smart_attendance_dashboard_screen.dart:99` : `try { parsedDate = DateTime.parse(dateLabel); } catch (_) {}` — mineur (fallback OK) mais muet.
- `leopardo_core/lib/offline/services/sync_service.dart:226,264` : `catch (_) { // Pull failures are silent — will retry next cycle }` — aucune trace (debugPrint) d'échec de sync Edge/Cloud.

---

## 6. Analyse statique — patterns qui cassent `flutter analyze`

### C18 🔴 P1 — `leopardo_platform_admin/lib/src/platform_admin_app.dart:19-24` : directives `import` après une déclaration
```dart
Locale? _resolvedLocale(Ref ref) { ... }   // lignes 14-17 (déclaration)
import 'features/companies/company_detail_screen.dart';  // ligne 19 — ILLÉGAL en Dart
import 'features/auth/platform_login_screen.dart';       // 20
...
```
Les directives d'import doivent précéder toute déclaration (grammaire Dart : `importOrExport*` avant `topLevelDeclaration*`) → erreur d'analyse/compilation (`flutter analyze` KO) ; le fichier est pourtant importé par `main.dart` (ligne 11) et utilisé. Résidu d'un merge (le commit 9fd1f0ce a touché ce fichier en dernier).

### C19 🟡 P3 — Vérifié non reproductible à HEAD
- `top_level_cycle` : introuvable — la dépendance circulaire apiClient ↔ authProvider a été traitée par lecture différée (`leopardo_employee/lib/core/providers/core_providers.dart:46-52`, commentaire issue #2737) ; aucun cycle de variables top-level.
- « Widget parse » : aucun pattern de ce type dans le code.
- HR possède bien une `ShellRoute` (app.dart:146-299, `HrMainShell`) — contrairement à l'ancien constat #3051.
- `themeMode: ThemeMode.system` dans les 5 apps — le forçage dark (#3053) est corrigé.

---

## 7. Authentification / session

### C20 🟡 P3 — Gestion 401 : OK sur les 3 apps principales, silencieuse sur marketing
Employee/manager/HR : `redirect` + `refreshListenable` (`app.dart:66-99`), 401 → `deleteToken` + `handleSessionExpired` (`api_client.dart:74-89` + core_providers.dart:46-52). Marketing : `onUnauthorized` = null → le 401 efface un token qui n'existe pas, sans navigation ni message (voir C5). Le kiosk n'étant pas une app Flutter, rien à auditer côté `leopardo_kiosk`.

---

## Synthèse par sévérité

| Sévérité | Nb | Références |
|---|---|---|
| 🔴 P1 | 6 | C1, C5, C10, C11, C12, C13 (+C18) |
| 🟠 P2 | 4 | C2, C6, C7, C14 |
| 🟡 P3 | 9 | C3, C4, C8, C9, C15, C16, C17, C19, C20 |

**Top 5 à traiter en priorité** :
1. C1 — routes manager manquantes (`/team`, `/tasks`, `/me/monthly`) → GoError au quotidien.
2. C12 — HR onboarding ne compile pas (int/String).
3. C13 — HR crash sur corrections + Manager erreur de type (`DateTime`).
4. C10/C11 — Firebase placeholder → push/init FCM mort sur 3-4 apps.
5. C5 — Marketing sans auth → app inutilisable (401).
