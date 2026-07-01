# Changelog

Toutes les modifications notables de ce projet sont documentées ici.
Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

## [4.18.0] - 2026-06-29

### Added
- **Module SmartAttendance** : pointage GPS automatique par géofencing
  - Phases 3/4/5 : Flutter geofencing (leopardo_employee), dashboard Manager/RH, tests Feature complets
  - API : endpoints `/api/v1/smart-attendance/*` (config, geo-events, sessions, validation, dashboard)
  - Modèles : `GeoAttendanceSession`, `EmployeeLocationEvent`, `AttendanceModeSettings`, `EmployeeAttendancePreferences`
  - Commande Artisan `smart-attendance:auto-close` — fermeture automatique des sessions GPS orphelines
  - Scheduler : `Schedule::command('smart-attendance:auto-close --hours=14')->hourly()`
  - Tests Feature : `AttendanceModeConfigTest`, `GeoEntryExitTest`, `GeoSessionDashboardTest`, `ManagerValidationTest`, `MultiTenantIsolationTest`
  - Trait de test `CreatesSmartAttendanceSchema` avec création/suppression des 4 tables
  - Flutter Employee : écran `SmartAttendanceScreen`, `GeofenceService` (Haversine), `BackgroundLocationService`
  - Flutter Manager/HR : feature `smart_attendance` avec écrans validation (liste pending + approve/reject), dashboard stats, navigation
  - Web dashboard : page `/smart-attendance` avec sessions listing, approbation et settings
  - Permissions Android : `ACCESS_BACKGROUND_LOCATION`, `FOREGROUND_SERVICE`, service `BackgroundWorker`
  - Permissions iOS : `NSLocationAlwaysAndWhenInUseUsageDescription`, `UIBackgroundModes` (fetch, processing)

## [4.17.0] - 2026-06-15

### Added
- Module IA : chat conversationnel et commandes vocales (léopardo_employee + leopardo_manager)
- Module Véhicules : suivi position GPS flotte (leopardo_manager)

### Fixed
- Correction timeout auth Sanctum sur reconnexion réseau mobile
