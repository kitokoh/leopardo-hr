# Feature Specification: Kiosk bridge local — durcissement sécurité & fiabilité sync

**Feature Branch**: `fix/3586-3587-3588-kiosk-bridge-hardening`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issues**: #3586 (P1 security), #3587 (P2 data-loss), #3588 (P2 reliability)

## Contexte

Le bridge desktop du kiosque ZKTeco (`front/zkteco-kiosk/desktop-bridge/bridge.py`)
sert l'UI offline-first et synchronise les pointages vers l'API. L'audit 360°
(qa-expert10) a relevé 3 défauts critiques : aucune authentification locale,
perte silencieuse de pointages skippés côté serveur, et un événement « poison »
capable de bloquer la file offline indéfiniment.

## User Stories & Testing

### User Story 1 — Bridge local authentifié (#3586, P1)

En tant qu'exploitant d'une borne sur LAN, je veux que le token kiosk et la PII
des employés ne soient pas lisibles par n'importe quel poste du réseau.

**Acceptance Scenarios**:
1. Given le bridge démarré, When `GET /config.json` ou `GET /desktop-bridge/data/kiosk.db`, Then 404 — les fichiers sensibles ne sont jamais servis (allowlist statique : `index.html`, `admin.html`, `app.js`, `admin.js`, `i18n.js` uniquement).
2. Given un path traversal (`/../`, préfixe sibling `zkteco-kiosk-*`), When requête reçue, Then 404 (résolution `Path.relative_to`, pas de préfixe-string).
3. Given un appel `/local/*` sans header `X-Local-Bridge-Token`, When reçu, Then 401.
4. Given un token invalide, When reçu, Then 401 (comparaison `hmac.compare_digest`).
5. Given une page servie par le bridge, When elle appelle `/local/*`, Then 200 — le token de session est injecté dans le HTML (`window.__LOCAL_BRIDGE_TOKEN`) et envoyé par `app.js`/`admin.js`.
6. Given un POST avec `Content-Type` non JSON ou un `Origin` cross-origin, When reçu, Then 415/403 (anti-CSRF `fetch no-cors`).
7. Given les pages HTML servies, When réponse, Then `Cache-Control: no-store` (jamais de HTML stale sans token).

### User Story 2 — Zéro perte silencieuse de pointages (#3587, P2)

En tant que RH, je veux que tout pointage refusé par le serveur soit visible
côté borne au lieu d'être marqué synchronisé.

**Acceptance Scenarios**:
1. Given un batch contenant un identifiant inconnu et un employé sans biométrie, When `POST /kiosks/{code}/sync`, Then la réponse contient `skipped[]` (external_event_id + identifier + reason : `EMPLOYEE_NOT_FOUND`, `BIOMETRIC_NOT_APPROVED`, `IDENTIFIER_REQUIRED`, `PUNCH_REJECTED`) en plus de `processed_count`, et chaque skip est journalisé (`Log::warning`).
2. Given une réponse avec `skipped[]`, When le bridge la traite, Then les événements skippés passent en `dead_letter` (jamais `synced`) avec la raison, les autres en `synced`.
3. Given un serveur legacy sans `skipped` et `processed_count < envoyés`, When le bridge détecte l'écart, Then les événements restent `queued` + `last_sync_error` renseigné (aucun marquage synced global).

### User Story 3 — File offline résiliente (#3588, P2)

En tant qu'exploitant, je veux qu'un événement corrompu soit isolé sans bloquer
les pointages suivants.

**Acceptance Scenarios**:
1. Given `POST /local/punch` avec `action`/`biometric_type` hors enum, When reçu, Then 422 immédiat (`INVALID_ACTION` / `INVALID_BIOMETRIC_TYPE`) — jamais inséré en file.
2. Given un 422 serveur avec erreurs `events.<i>.<field>`, When le bridge parse la réponse, Then les événements fautifs passent en `dead_letter` et le reste du batch est renvoyé une fois.
3. Given un 4xx non analysable sur un batch d'un seul événement, When reçu, Then l'événement passe en `dead_letter`.
4. Given des 5xx/erreurs réseau répétées, When `retry_count` atteint le cap (10), Then l'événement passe en `dead_letter` ; entre-temps backoff exponentiel (`next_retry_at`, base 15 s, max 15 min).
5. Given des événements en `dead_letter`, When `GET /local/status`, Then `dead_letter_count` exposé ; When `POST /local/events/requeue`, Then l'événement repasse en `queued` (réparation ops).
6. Given une base `kiosk.db` existante, When le bridge démarre, Then les colonnes `retry_count`/`next_retry_at` sont ajoutées par migration douce (`PRAGMA table_info` + `ALTER TABLE`).

## Out of Scope

- Refonte UI de la page admin locale (le statut dead-letter est exposé en JSON).
- Changement du contrat `X-Kiosk-Token` côté cloud.
