# Feature Specification: Kiosk Bridge — sécurité & fiabilité offline (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Audit 360° du 2026-08-15 — surface kiosque ZKTeco jamais auditée par les vagues précédentes.

## User Scenarios & Testing

### US1 — Verrouillage du bridge local (Priority: P1) — Issues #3586
En tant qu'exploitant d'une borne ZKTeco sur LAN, je veux que le bridge HTTP local n'expose ni le token kiosk ni la base de pointages, et qu'aucun pointage ne puisse être forgé sans secret, afin d'empêcher fraude et fuite de PII.

**Acceptance Scenarios**:
1. **Given** le bridge en écoute, **When** `GET /config.json` ou `GET /desktop-bridge/data/kiosk.db`, **Then** 403/404 (jamais de contenu).
2. **Given** une requête `POST /local/punch` sans token de session local, **When** elle arrive, **Then** 401.
3. **Given** une page web hostile, **When** `fetch('http://127.0.0.1:8037/local/punch', no-cors, text/plain)`, **Then** rejetée (Content-Type/Origin vérifiés).

### US2 — Aucune perte silencieuse de pointages (Priority: P2) — Issue #3587
Le bridge ne marque synced que les événements réellement traités (`processed_count`), les skips serveur sont remontés avec raison.

### US3 — File offline anti-poison (Priority: P2) — Issue #3588
Validation des enums à l'insertion, distinction 4xx/5xx, dead-letter + UI de reprise.

### US4 — Hygiène config (Priority: P3) — Issue #3590
`config.json` gitignoré, `apiBaseUrl` normalisé partout, health check léger au lieu du roster.

## Requirements
- FR-1: bind 127.0.0.1 par défaut ; allowlist de fichiers statiques (jamais config.json / *.db)
- FR-2: token de session local sur tous les `/local/*` ; vérif Origin + Content-Type sur POST
- FR-3: sync compare processed_count ; serveur renvoie les skips (identifiant + raison)
- FR-4: dead-letter queue + backoff + retry cap
- FR-5: .gitignore config.json ; normalisation apiBaseUrl partagée ; poll /health
