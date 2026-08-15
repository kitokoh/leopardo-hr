# Implementation Plan: Kiosk bridge local — durcissement

**Spec**: `.specify/features/kiosk-bridge-local-hardening/spec.md`
**Branch**: `fix/3586-3587-3588-kiosk-bridge-hardening`

## Architecture

### Bridge (`front/zkteco-kiosk/desktop-bridge/bridge.py`)

- `LOCAL_BRIDGE_TOKEN = secrets.token_urlsafe(32)` généré par boot, injecté dans
  le HTML servi (`window.__LOCAL_BRIDGE_TOKEN`), exigé via header
  `X-Local-Bridge-Token` sur tout `/local/*` (comparaison `hmac.compare_digest`).
- Guard POST : `Content-Type: application/json` obligatoire ; si `Origin`
  présent, son host doit égaler le header `Host`.
- Statique : allowlist exacte `{index.html, admin.html, app.js, admin.js,
  i18n.js}` ; contrôle de confinement via `Path.relative_to(ROOT)`.
- `punch_queue` : +`retry_count INTEGER NOT NULL DEFAULT 0`,
  +`next_retry_at TEXT` (migration douce `PRAGMA table_info`/`ALTER TABLE`),
  statut `dead_letter`.
- `SyncEngine.upload_events` : succès → `mark_synced` des non-skippés +
  `mark_dead_letter` des `skipped[]` ; `HTTPError` 4xx → isolation poison via
  clés `events.<i>.*` du corps Laravel ; 5xx/réseau → `mark_retry` (backoff
  exponentiel, cap 10 → dead-letter).

### API (`api/`)

- `KioskAttendanceService::syncPunches` retourne
  `['processed' => int[], 'skipped' => array{external_event_id, identifier, reason}]`
  au lieu de `int[]` ; chaque skip est journalisé (`Log::warning`, sans PII au-delà
  de l'identifiant saisi). Les rejets métier par événement (`MissingCheckInException`,
  `ModelNotFoundException`, `HttpException` 403) sont capturés par événement — plus
  de 500 global pour un seul événement fautif.
- `KioskController::doSync` expose `skipped` + `skipped_count` (contrat additif,
  `processed_count` inchangé).
- `api/openapi.yaml` : réponse sync documentée.

### Front kiosk (`app.js`, `admin.js`)

- `localFetchJson()` : enveloppe `fetchJson` qui ajoute
  `X-Local-Bridge-Token: window.__LOCAL_BRIDGE_TOKEN` pour les appels `/local/*`.

## Tests

- `front/zkteco-kiosk/tests/test_bridge_security.py` (unittest, CI) :
  allowlist statique, traversal, 401/403/415/422, injection token, requeue.
- `api/tests/Feature/KioskSyncSkippedEventsTest.php` : skipped list + raisons.
- `kiosk-ci.yml` : step `python3 -m unittest` ajouté.

## Risques

- HTML caché sans token → 401 : mitigé par `Cache-Control: no-store`.
- Legacy server sans `skipped` : fallback conservateur (queued + warning), la
  dead-letter par cap de retries borne la rétention.
