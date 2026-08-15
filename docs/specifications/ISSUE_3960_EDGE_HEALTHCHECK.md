# ISSUE_3960 — Healthchecks Edge : probe /api/edge/health (404 permanent)

**Statut**: Fixed (PR `fix/3960-edge-healthcheck-path`) · **Priorité**: P2 · **Module**: edge-sync

## Correctif

`edge/Dockerfile.publish` HEALTHCHECK : `/api/edge/health` →
`/api/v1/edge/health` (route réelle `EdgeController@health`, prefix
`api/v1/edge`). `docker-compose.yml` était déjà aligné (#3908).
