# Feature Specification: RTMX client — If-None-Match + Idempotency-Key dans les apps (#5407)

**Feature Branch**: `mod/platform/5407-rtmx-client`

**Created**: 2026-08-24

**Status**: Implementation

**Input**: Issue #5407 — [P2][mobile] RTMX client — If-None-Match + Idempotency-Key dans les apps (suite #5277).
Références : spec serveur `.specify/features/5277-rtmx/spec.md` (mergée — `HttpCacheMiddleware` ETag/304 +
`IdempotencyMiddleware` `Idempotency-Key`, 24 h, scopé par token) ; `docs/plan/PLAN_100PCT.md` §6.3.

## Problème

Le socle serveur RTMX (#5277) est mergé : `HttpCacheMiddleware` (GET conditionnels ETag/304,
`Cache-Control: private, max-age=0, must-revalidate`) et `IdempotencyMiddleware` (rejeu sûr des
écritures via header `Idempotency-Key`, 1ʳᵉ réponse 2xx rejouée 24 h, clé scopée par token, 409/422).

Les apps mobiles (`leopardo_employee`, `leopardo_hr`, `leopardo_manager`) passent déjà par
`/attendance/*` avec une file hors-ligne (`offline_punches`, règle F-21 « 1er pointage gagne »)
rejouée par `OfflineSyncService` (`leopardo_core`), **mais** :

- Les écritures (`check-in`, `check-out`, geo-events) n'envoient **aucun** `Idempotency-Key` :
  un rejeu réseau (timeout après commit serveur, double POST de la file hors-ligne) peut créer un
  doublon côté serveur (le middleware ne déduplique que les requêtes portant la clé).
- Les lectures (`/attendance/today`, `/attendance/config`, `/me/*`) n'envoient **aucun**
  `If-None-Match` et un 304 éventuel serait traité comme une erreur par Dio (validateStatus par
  défaut) — le bénéfice bande passante/latence du serveur n'est pas consommé.

## Décision

Bascule **client** du protocole RTMX, côté `front/mobile_apps/**` uniquement (aucun changement de
contrat serveur — les headers sont opt-in) :

1. **Clé d'idempotence par pointage logique** — nouveau helper `IdempotencyKeys.newKey()`
   (`leopardo_core/lib/core/api/idempotency_keys.dart`, UUID v4 via package `uuid` — conforme au
   motif serveur `[A-Za-z0-9._:-]{8,255}`). Chaque action de pointage (check-in, check-out,
   geo-event) génère UNE clé au début de l'appel, l'envoie en header `Idempotency-Key`, et la
   **stocke dans l'entrée de la file hors-ligne** (`offline_punches` → champ `idempotencyKey`)
   pour que `OfflineSyncService.syncPendingPunches()` réutilise la MÊME clé au rejeu → le serveur
   rejoue la 1ʳᵉ réponse au lieu de créer un doublon.
2. **Cache ETag/304 dans `ApiClient`** (`leopardo_core/lib/core/api/api_client.dart`) : pour les
   GET, mémoriser en mémoire `ETag` + corps par URL ; envoyer `If-None-Match` sur les relectures ;
   étendre `validateStatus` pour accepter 304 et traiter le 304 comme un succès dont le corps est
   le corps caché. Cache vidé à la déconnexion/401 (aucune fuite inter-session).

**Pourquoi ce choix** : zéro changement serveur (headers opt-in, rétro-compatibles), zéro changement
métier ; la file hors-ligne existante (`offline_punches` + `OfflineSyncService`) devient
idempotente sans changer sa sémantique F-21.

## Périmètre

### Dans le périmètre

- `front/mobile_apps/leopardo_core/lib/core/api/idempotency_keys.dart` (nouveau)
- `front/mobile_apps/leopardo_core/lib/core/api/api_client.dart` (cache ETag GET + 304 + clear)
- `front/mobile_apps/leopardo_core/lib/core/services/offline_sync_service.dart` (rejeu même clé)
- `front/mobile_apps/leopardo_core/pubspec.yaml` (dépendance `uuid`)
- 3 × `features/attendance/data/attendance_repository.dart`
  (`leopardo_employee`, `leopardo_hr`, `leopardo_manager`) — clé + header + stockage file
- `leopardo_employee/lib/features/smart_attendance/data/smart_attendance_repository.dart`
  (`sendGeoEvent` → header `Idempotency-Key`)
- Tests : `leopardo_core/test/offline/offline_sync_service_test.dart`,
  `leopardo_core/test/api/api_client_http_cache_test.dart` (nouveau),
  `leopardo_{employee,hr,manager}/test/features/attendance/*` (header + clé stockée)
- `CHANGELOG.md` (1 ligne en tête d'`[Unreleased]`)

### Hors périmètre

- Serveur `api/**` (aucun changement — middleware déjà mergés #5277).
- Module `Attendance`/`SmartAttendance` serveur (agents #5355/#5265) — pas de route ni service.
- `leopardo_core/lib/offline/**` (`AttendanceOfflineService`, drift/edge) — appareils Edge, hors
  file `offline_punches`.
- App mobile `leopardo_accounting` (#5236) — bloquée sur la convergence #2601.
- Décision « PWA ou app native » (#5277) — non bloquante.

## User Scenarios & Testing

### US1 — Le pointage hors-ligne rejoué ne crée jamais de doublon (Priority: P1)

Un check-in hors-ligne est rejoué avec la MÊME `Idempotency-Key` que celle de l'appel initial ;
le serveur rejoue la 1ʳᵉ réponse 2xx (24 h) au lieu de créer un second pointage.

**Independent Test**: `OfflineSyncService` avec une entrée `offline_punches` portant
`idempotencyKey` → le sender reçoit cette clé à l'identique ; une entrée sans clé → `null`.

**Acceptance Scenarios**:

1. **Given** un check-in hors-ligne mis en file avec sa clé, **When** `syncPendingPunches()` le
   rejoue, **Then** le header `Idempotency-Key` envoyé est celui stocké (pas une nouvelle clé).
2. **Given** une entrée de file existante SANS clé (données pré-#5407), **When** elle est rejouée,
   **Then** aucun header `Idempotency-Key` (rétro-compatibilité).
3. **Given** un check-in en ligne, **When** l'appel part, **Then** le header `Idempotency-Key`
   (UUID v4) est présent et identique à celui stocké en cas de bascule hors-ligne.
4. **Given** un geo-event (`sendGeoEvent`), **When** l'appel part, **Then** le header
   `Idempotency-Key` (UUID v4) est présent.

### US2 — Les lectures sont conditionnelles (ETag/304) (Priority: P2)

`ApiClient` mémorise `ETag` + corps par URL (GET) ; la relecture envoie `If-None-Match` et un
304 est traité comme un succès avec le corps caché.

**Independent Test**: premier GET → `200` + `ETag` + corps ; second GET → le client envoie
`If-None-Match` et un `304` est résolu avec le corps du premier GET.

**Acceptance Scenarios**:

1. **Given** un GET `2xx` avec header `ETag`, **When** la même URL est relue, **Then**
   `If-None-Match` est envoyé et un 304 retourne le corps caché (pas d'erreur).
2. **Given** un GET `2xx` sans header `ETag`, **When** la même URL est relue, **Then** aucun
   `If-None-Match` (pas de cache sans ETag).
3. **Given** un contenu modifié (nouvel ETag), **When** la même URL est relue, **Then** le nouveau
   corps remplace l'ancien en cache.
4. **Given** une déconnexion/401, **When** une nouvelle session démarre, **Then** le cache est
   vidé (aucune fuite inter-session).

### US3 — Rétro-compatibilité (Priority: P1)

**Independent Test**: les tests existants (`offline_sync_service_test`, `attendance_repository_test`
×3 apps) restent verts ; aucun contrat serveur modifié (headers opt-in).

**Acceptance Scenarios**:

1. **Given** un serveur sans RTMX (ancien déploiement), **When** les apps envoient les headers,
   **Then** le comportement est identique (headers ignorés).
2. **Given** un client sans cache (première exécution), **When** il lit une URL, **Then** aucun
   `If-None-Match` n'est envoyé.

## Critères d'acceptation (DoD #5407)

- [ ] Un pointage hors-ligne rejoué après coupure ne crée jamais de doublon (clé rejouée,
      testée dans `offline_sync_service_test`)
- [ ] `flutter analyze` 0 issue ×4 packages (`leopardo_core`, `leopardo_employee`,
      `leopardo_hr`, `leopardo_manager`)
- [ ] Tests mobiles verts (core + 3 apps)
- [ ] Aucun changement de contrat serveur (headers opt-in)
- [ ] CHANGELOG : 1 ligne en tête d'`[Unreleased]`

## Procédure de recette pilote

1. Passer en mode avion : pointer → entrée `offline_punches` avec `idempotencyKey`.
2. Revenir en ligne : `OfflineSyncService` rejoue avec la même clé → vérifier dans
   `GET /attendance/today` qu'un seul pointage existe (pas de doublon).
3. Réseau dégradé (throttle) : rouvrir l'écran pointage → la relecture envoie `If-None-Match`,
   le serveur répond 304, le rendu utilise le corps caché (< 3 s).
