# ISSUE_3972 — docker-compose edge : tags flottants

**Statut**: Fixed (PR `fix/3972-edge-compose-pinned`) · **Priorité**: P3 · **Module**: edge-sync

## Correctif

`edge/docker-compose.yml` : `leopardo/edge-api|edge-ui:${EDGE_VERSION:-1.0.0}`
(aligné sur `edge/publish.sh`) au lieu de `:latest` ; Caddy pinné par digest
multi-arch `sha256:5f5c8640…` (vérifié via l'API registry 2026-08-15).
Note : les images Leopardo doivent être publiées (publish.sh) sous ce tag.
