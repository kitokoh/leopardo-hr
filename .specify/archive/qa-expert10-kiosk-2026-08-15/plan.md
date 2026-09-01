# Implementation Plan: Kiosk Bridge

**Technical Context**: Python stdlib HTTP server (`desktop-bridge/bridge.py`), UI statique (`app.js`), API Laravel (`KioskController`, `KioskAttendanceService`).

## Approach
1. `bridge.py` : allowlist statique (index.html/app.js/i18n.js/assets), refus `config.json`/`*.db`, résolution de chemin par `Path.relative_to` au lieu du préfixe-string.
2. Token local : généré au 1er boot, stocké `data/local_token` (chmod 600), exigé via header `X-Local-Token` ; vérif `Content-Type: application/json` + `Origin` absent ou `null`.
3. Sync : parser `processed_count` ; laisser en queue les ids non traités ; serveur : liste `skipped` dans la réponse.
4. Validation enums (`check_in/check_out`, `fingerprint/face/mixed`) à l'insertion ; statut `dead_letter` + endpoint `/local/dead-letter` ; backoff exponentiel capé.
5. `.gitignore` kiosk : `config.json` ; extraire `normalizeApiBaseUrl()` côté bridge ; poll `GET /api/v1/health`.

## Tests
- Unitaires bridge : traversal refusé, POST sans token 401, poison → dead_letter.
- Feature API : sync avec identifiant inconnu → réponse contient `skipped`.
