# ISSUE_3862 — zone_enter perdu silencieusement (catch muet)

**Statut**: Fixed (PR `fix/3862-zone-enter-error-visible`) · **Priorité**: P2 · **Module**: mobile

## Correctif

`background_location_service.dart` :
- `_sendGeoEvent` : catch loggé (`debugPrint` avec raison) + `_enqueueGeoEvent`
  (file bornée `_maxPendingGeoEvents = 20`, garde les plus récents) ;
- `_flushPendingGeoEvents` : rejoue la file au début de chaque `_performCheck`
  (best-effort, `break` au premier échec — ne bloque jamais le tick) ;
- classe `_PendingGeoEvent` (eventType, lat/lng, accuracy, createdAt, error).
