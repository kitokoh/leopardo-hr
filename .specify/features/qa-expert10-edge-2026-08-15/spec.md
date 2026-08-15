# Feature Specification: Edge — installation client fonctionnelle (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Audit 360° du 2026-08-15 — le parcours d'installation d'un nœud Edge est inutilisable en production.

## User Scenarios & Testing

### US1 — Installation de bout en bout (Priority: P1) — Issue #3591
En tant que client, je veux exécuter `install.sh` et obtenir une stack Edge fonctionnelle.

**Acceptance Scenarios**:
1. **Given** l'API en prod, **When** `GET /api/v1/edge/download/install.sh` et `.../docker-compose.yml`, **Then** 200 (pas 404).
2. **Given** le compose téléchargé, **When** `docker compose pull`, **Then** toutes les images existent (dont `leopardo/edge-ui`).

### US2 — Configuration cohérente (Priority: P2) — Issue #3592
Domaine cloud unique, Caddyfile standalone qui sert la PWA, APP_KEY persistée, healthchecks réels.

## Requirements
- FR-1: embarquer install.sh/compose/clé publique dans l'image API (ou artefact de release)
- FR-2: publish.sh build+push `leopardo/edge-ui` versionné
- FR-3: source unique du domaine cloud (config + docs alignées)
- FR-4: Caddyfile standalone → PWA + proxy localhost
- FR-5: APP_KEY persistée dans /data au 1er boot
- FR-6: healthchecks PHP+SQLite sur les 4 services
