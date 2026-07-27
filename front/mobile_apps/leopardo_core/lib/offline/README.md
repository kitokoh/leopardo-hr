# Leopardo Offline — Module Flutter

Support offline-first pour les apps Leopardo RH (employee + manager).

> **Statut (issue #1287)** : ce module est cable dans `leopardo_employee`
> (`app.dart` + `core/providers/core_providers.dart`). `SyncService` demarre
> automatiquement au lancement de l app et bascule en mode Edge une fois que
> l utilisateur a renseigne l URL/identifiant/jeton du noeud Edge dans
> **Parametres → Noeud Edge**. Les autres apps mobiles (`manager`, `hr`,
> `platform_admin`) n en ont pas encore besoin cote produit et restent en
> mode Cloud/Offline (fallback Hive `offline_punches` existant).

## Architecture

```
lib/offline/
├── database/
│   └── edge_database.dart          # Base SQLite locale (Drift)
├── services/
│   ├── sync_service.dart           # Détection mode + sync
│   └── offline_token_service.dart  # JWT sans Cloud
└── widgets/
    └── sync_status_banner.dart     # Bandeau statut UI
```

## Modes de fonctionnement

| Mode | Description |
|------|-------------|
| `SyncMode.cloud` | Internet direct → pas de bandeau |
| `SyncMode.edge` | Réseau local Edge → bandeau orange |
| `SyncMode.offline` | Hors ligne total → bandeau rouge |

## Intégration dans une app

```dart
// main.dart ou App widget — voir l implementation reelle dans
// leopardo_employee/lib/core/providers/core_providers.dart (syncServiceProvider)
final db = EdgeDatabase();
final syncService = SyncService(
  db: db,
  dio: Dio(),
  edgeBaseUrl: 'http://leopardo.local:7878',
  cloudBaseUrl: 'https://api.leopardo.app',
  // Cloud-issued UUID for this Edge node (distinct from edgeToken).
  // Obtained at enrollment together with the token — see the
  // `install_command` returned by `POST /api/v1/edge` (Cloud admin API).
  // Saved via AppPreferences.saveEdgeEnrollment() from Settings.
  edgeNodeId: preferences.edgeNodeId,
  edgeToken: preferences.edgeToken,
);
syncService.start();

// Dans un Scaffold
Column(
  children: [
    LiveSyncStatusBanner(
      syncService: syncService,
      onSyncTap: () => syncService.syncNow(),
    ),
    // ... reste de l'app
  ],
)
```

## Générer le code Drift

```bash
cd front/mobile_apps/leopardo_core
dart run build_runner build --delete-conflicting-outputs
```

## Tables locales

| Table | Rôle |
|---|---|
| `LocalAttendanceLogs` | Pointages offline |
| `LocalAbsences` | Demandes d'absence offline |
| `LocalEmployees` | Cache employés (read-only depuis Cloud) |
| `LocalSyncQueue` | Queue de sync vers Edge/Cloud |
| `LocalDepartments` | Cache départements |
