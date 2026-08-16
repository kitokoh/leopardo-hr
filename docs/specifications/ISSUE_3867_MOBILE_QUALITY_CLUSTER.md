# Issue #3867 — Cluster qualité mobile (deep links, URLs, SyncService, catch muets)

## Correctifs

1. **Deep links** : intent-filters `VIEW` sur le manifest employee
   (`leopardo://employee/<route>` + `https://app.leopardo-rh.com/employee/*`) —
   convention documentée ; les routes sont résolues par GoRouter.
2. **URLs hardcodées** : `SyncService.defaultEdgeBaseUrl` centralise le fallback
   `leopardo.local:7878` (utilisé par les 3 providers) ; l'API client centralise
   déjà les baseUrl (kDebugMode → localhost, sinon remote).
3. **SyncService** : subscription `Connectivity().onConnectivityChanged` conservée
   et `cancel()` dans `stop()` (fuite à chaque cycle de vie) ; `stop()` idempotent
   (garde `_stopped` + `isClosed`).
4. **Catch muets** : les `catch (_)` de pull/probe loggés via `debugPrint`
   (contexte + runtimeType) — diagnostic terrain possible sans bruit prod.

## Critères de succès

- [x] Intent-filters présents (employee)
- [x] Aucune URL hardcodée hors config/constante
- [x] SyncService sans fuite (dispose/stop cancel)
- [x] Pas de catch muet restant dans sync_service.dart
- [ ] `flutter analyze` vert (CI mobile)
