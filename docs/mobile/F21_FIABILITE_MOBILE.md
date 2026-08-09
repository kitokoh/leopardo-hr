# F-21 — Fiabilité mobile employee : hors-ligne, géofencing, anti-fraude (#1551)

> Programme FOCUS F-21 (#1551) — état réel 2026-08-09.
> App cible : `front/mobile_apps/leopardo_employee` + socle `leopardo_core`.

## 1. Mode hors-ligne

Le pointage doit rester possible sans connexion, puis se synchroniser
automatiquement sans perte.

| Élément | Où | Comportement |
|---|---|---|
| Détection de mode | `leopardo_core/lib/offline/services/sync_service.dart` (`SyncMode.cloud/edge/offline`) | Sonde `/api/v1/edge/health` (Edge local) puis `/api/v1/health` (Cloud) ; bascule offline si aucune joignable. |
| File locale | `leopardo_core/lib/offline/database/edge_database.dart` (`local_sync_queue`, drift) + repli Hive `offline_punches` dans `leopardo_employee/lib/features/attendance/data/attendance_repository.dart` | Check-in/out hors-ligne → enregistrement local `status=offline_sync_pending`. |
| Sync automatique | `SyncService.start()` / `_onConnectivityChangedList` / timer 5 min | Au retour de connectivité, `syncNow()` pousse par lots de 50 vers `/api/v1/edge-node/{id}/push`. |
| Résolution de conflits | `api/app/Modules/EdgeSync/Application/Services/SyncEngineService.php` | Règle « 1er pointage gagne » : les pointages sont additifs, dédoublonnés par `external_event_id` ; `attendance_logs → local_wins` (accepté tant que sûr), absences approuvées → cloud wins. |

Tests : `leopardo_core/test/offline/sync_service_test.dart`,
`leopardo_core/test/offline/attendance_offline_service_test.dart`,
`leopardo_employee/test/features/attendance/attendance_repository_test.dart`
(check-in/out hors-ligne → file, erreur non-réseau propagée).

## 2. Géofencing

| Élément | Où | Comportement |
|---|---|---|
| Calcul de zone | `leopardo_employee/lib/features/smart_attendance/services/geofence_service.dart` | Haversine, état interne (entrée/sortie), `isCurrentlyInside`, `reset()`. |
| Rayon par site | Config backend `SmartAttendanceConfig` (`gps_enabled`, `latitude`, `longitude`, `radius`) | `hasValidZone` = lat/lng/rayon présents et rayon > 0 ; GPS désactivé → aucun événement. |
| Événements | `ZoneEvent.enter/exit/none` | Transition détectée à la frontière (distance ≤ rayon = dedans, à l'horizon exact inclus). |

Tests : `leopardo_employee/test/features/smart_attendance/geofence_service_test.dart`
(dans / hors / horizon / config invalide / distance).

## 3. Anti-fraude

| Élément | Où |
|---|---|
| Photo au check-in | `image_picker` + champ `punch_photo` (API pointage, mode kiosk/mobile) |
| Double check-in bloqué | Contrôle serveur (un pointage ouvert par jour/employé) |
| Horodatage serveur | Le serveur consigne l'heure de réception ; le mobile envoie `device_timezone` |
| Geo-attendance | Sessions géo (`geo_attendance_session.dart`, backend SmartAttendance) |

## 4. Critères d'acceptation — mapping

- [x] Check-in hors-ligne puis sync sans perte → `SyncService` + `AttendanceOfflineService` + tests.
- [x] Géofencing testé (dans / hors / horizon) → `geofence_service_test.dart`.
- [x] Widget/unit tests critiques verts en CI → `flutter test` branché sur `mobile-apps-ci.yml` (#1560/#1645) pour `leopardo_employee`.
- [x] Docs utilisateur → ce document + `docs/mobile/CONVERGENCE_F27.md`.

## 5. Limites connues

- Le mode Edge (borne locale) nécessite un nœud Edge provisionné (`edge-node` + token) ; sans Edge, le mode Cloud est utilisé.
- La tolérance GPS (précision du capteur) n'est pas encore appliquée côté mobile (la décision d'entrée/sortie utilise la position brute) — piste F-21 suivante.
- `local_sync_queue` est partagé avec le module Edge ; l'identifiant local est un UUID v4 aléatoire (fix #1611) pour éviter les collisions.
