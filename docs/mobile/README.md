# Mobile Application Documentation

This directory contains resources and guides for the Leopardo RH Flutter mobile application.

## 📱 Overview
The mobile app is the primary touchpoint for employees and on-site managers. It handles:
- Biometric & GPS-validated attendance.
- Personal payroll summaries.
- Team task management.
- Real-time notifications.

## 📱 Applications reelles
La doc canonique par app vit dans [`front/mobile_apps/README.md`](../../front/mobile_apps/README.md) :
`leopardo_core` (package partage), `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`.
Le mobile historique (`front/mobile/`) a ete retire du depot.

## 📶 Fiabilité mobile employee (F-21, #1551)

L'app `leopardo_employee` est conçue pour pointer de façon fiable sur le
terrain, même avec une connexion coupée ou un GPS approximatif.

### Mode hors-ligne
- **File d'attente locale** : un check-in/out échouant pour cause réseau est
  mis en file dans Hive (`offline_punches`) puis dans la file Drift
  (`EdgeDatabase`) via `AttendanceOfflineService`.
- **Sync automatique** : au retour de la connexion, `OfflineSyncService`
  (file Hive) et `SyncService` (file Drift, modes cloud/edge/offline
  détectés par sonde de santé) rejouent la file sans perte.
- **Conflits — règle « 1er pointage gagne »** : le serveur déduplique les
  pointages par `external_event_id` (`SyncEngineService::applyAttendanceLog`) ;
  un doublon rejoué est ignoré, le premier pointage fait foi.

### Géofencing
- Zone par site : centre + rayon configurés par l'entreprise
  (`SmartAttendanceConfig`), calcul Haversine (`GeofenceService`).
- **Tolérance GPS** : une position dont la précision dépasse
  `max(50 m, rayon de la zone)` est ignorée (aucun événement entrée/sortie,
  état inchangé) — évite les faux positifs au voisinage de l'horizon.
- Polling batterie-économe : position unique toutes les 5 min
  (`LocationAccuracy.medium`), saut de mesure si déplacement < 100 m.

### Anti-fraude
- Photo au check-in (kiosque/mobile), double check-in bloqué (serveur),
  horodatage serveur, événements GPS horodatés.

Tests critiques associés : `geofence_service_test.dart` (dans/hors/horizon,
transitions, tolérance), `attendance_repository_test.dart` (file hors-ligne,
erreurs réseau), `attendance_offline_service_test.dart` / `sync_service_test.dart`
(core).

## 🛠 Setup & Development
- [Mobile Apps Guide](../../front/mobile_apps/README.md) - Structure, regles de contribution, CI, identites store.
- [Design System](../vision/02_design_system/README.md) - UI/UX tokens and components.

---

For technical support, see [SUPPORT.md](../../SUPPORT.md) (the Discord invite is not live yet).
