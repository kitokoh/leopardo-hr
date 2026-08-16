# Feature Specification: Mobile — SyncService Edge timeouts par défaut (issue #4406)

**Feature Branch**: `fix/4406-syncservice-timeouts`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA — les providers (employee/manager/hr) passent `Dio()` nu à
`SyncService` (aucun BaseOptions, aucun timeout) ; `_dio.post`/`_dio.get` n'ont pas
de borne par requête (seul `_ping` a 3 s). Si l'Edge box accepte le TCP et pend, la
requête attend indéfiniment.

## Problème

`leopardo_core/lib/offline/services/sync_service.dart` : `syncNow()` et `_pullDelta()`
utilisent le `Dio` injecté sans timeout → sync offline morte sans reprise si la
connexion pend. (Le `finally { _isSyncing = false; }` est déjà en place sur main.)

## Décision

- Dans le constructeur de `SyncService` : bornes par défaut sur le `Dio` injecté
  (connect 10 s, receive 30 s) via `??=` — ne remplace pas une config explicite.
- `_ping` garde son timeout court par requête (3 s) qui prime sur les défauts.
- Test de régression dans `sync_service_test.dart`.

## User Scenarios & Testing

### User Story 1 — Une requête pendante ne bloque jamais la sync (Priority: P2)

**Independent Test**: `flutter analyze` + `flutter test` (leopardo_core) via
mobile-apps-ci ; test dédié assertant les timeouts par défaut après construction.

**Acceptance Scenarios**:

1. **Given** un `SyncService` construit avec un `Dio()` nu, **When** on lit
   `dio.options.connectTimeout`, **Then** 10 s (et receiveTimeout 30 s).
2. **Given** un `Dio` déjà configuré avec ses propres timeouts, **When** le service
   est construit, **Then** les valeurs existantes ne sont pas écrasées (`??=`).
3. **Given** une requête pendante, **When** le timeout expire, **Then** `syncNow()`
   rend la main (DioException → batch marqué failed) et `_isSyncing` est réinitialisé
   (déjà couvert par le `finally`).

## Edge Cases

- `Dio` partagé par d'autres consommateurs : les défauts ne font qu'ajouter des bornes
  (aucun comportement retiré).
- Tests avec `FakeHttpAdapter` : le `Dio` réel est utilisé → les défauts s'appliquent
  sans casser les mocks.
