# Issue #4199 — Repositories ré-implémentent le déballage d'enveloppe

## Problème

`extractDataList()` / `extractDataMap()` (`leopardo_core/core/api/api_payload.dart`)
sont la règle (AGENTS.md v4.16.201), mais 4 fichiers par app déballaient l'enveloppe
à la main, avec des variantes divergentes.

## Correctif

- `notification_repository.dart` ×3 : `payload is Map ? payload['data'] : payload`
  → `extractDataList(payload)` (+ import `api_payload.dart`).
- `attendance_repository.dart` ×3 : helper privé `_dataMap` (copie exacte
  d'`extractDataMap`) supprimé, appels → `extractDataMap(...)` ; bloc
  `raw['data']` (manager/hr) → `extractDataMap(response.data)`.
- `smart_attendance_repository.dart` (employee) : `_extractData` privé supprimé
  → `extractDataMap(...)` (manager/hr utilisaient déjà les helpers).
- Cas structurés conservés et **documentés** : `decodeTodayResponse`
  (distinction null nécessaire), `user_auth` (parse multi-formes token racine /
  data / auth), `auth_repository` (contrat login {token, employee} hors enveloppe).

## Critères de succès

1. Grep de garde : plus aucun déballage manuel dans les 4 fichiers × 3 apps.
2. `flutter analyze` ×3 apps : 0 erreur (aucun helper privé mort restant).
3. Tests de repositories existants verts (comportement inchangé).
