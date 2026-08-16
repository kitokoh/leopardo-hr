# Feature Specification: SyncService — timeouts bornés (Closes #4406)

**Feature Branch**: `fix/4406-syncservice-timeout`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4406 (P2, mobile, edge-sync — canal offline peut mourir en silence)

## Contexte

`leopardo_employee` injecte `Dio()` nu dans `SyncService` (aucun timeout).
Une connexion qui accepte le TCP puis pend fait attendre `_dio.post`/`_dio.get`
pour toujours : `syncNow()` ne rend jamais la main, `_isSyncing` reste `true`
et le `Timer.periodic` 5 min retourne `skipped()` à chaque tick — la sync
offline meurt sans reprise ni alerte.

## User Stories & Testing

### User Story 1 — Une requête pendante ne tue jamais le canal (P1)

**Acceptance Scenarios**:
1. Given un Dio nu injecté, When construction du service, Then
   `connectTimeout=10s`, `receiveTimeout=30s` forcés sur le Dio.
2. Given une requête qui pend, When push/pull, Then DioException au timeout →
   items marqués failed (retry) → `_isSyncing` relâché (finally).
3. Given le tick suivant, When syncNow, Then nouvelle tentative (plus de
   `skipped()` permanent).

## Requirements

### Functional Requirements

- **FR-001**: le constructeur de `SyncService` copie les options du Dio injecté
  avec `connectTimeout: 10s`, `receiveTimeout: 30s`.
- **FR-002**: `_isSyncing` relâché en `finally` (déjà en place — verrouillé par test).
- **FR-003**: test unitaire des timeouts forcés (Dio nu).

## Success Criteria

- **SC-001**: `sync_service_test.dart` vert (timeouts assertés).
- **SC-002**: `flutter analyze` vert sur leopardo_core.
