# Leopardo Offline — Module Flutter

Support offline-first pour les apps Leopardo RH (employee + manager).

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
// main.dart ou App widget
final db = EdgeDatabase();
final syncService = SyncService(
  db: db,
  dio: Dio(),
  edgeBaseUrl: 'http://leopardo.local:7878',
  cloudBaseUrl: 'https://api.leopardo.app',
  edgeToken: prefs.getString('edge_token') ?? '',
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
