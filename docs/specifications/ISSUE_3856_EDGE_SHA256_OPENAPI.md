# ISSUE_3856 — Drift OpenAPI : endpoints EdgeSync .sha256 non documentés

**Statut**: Fixed (PR `fix/3856-openapi-edge-sha256`) · **Priorité**: P1 · **Module**: api/edge

## Constat

`check-openapi-route-coverage.py` (#1473) échoue sur main : 3 routes live
(`GET /api/v1/edge/download/sha256.txt`, `docker-compose.yml.sha256`,
`Caddyfile.edge.sha256`) ajoutées par le lot Edge (#3770/#3529) ne sont ni dans
`api/openapi.yaml` ni dans l'allowlist.

## Correctif

Les 3 routes documentées sous tag `Health` (publiques, `text/plain`), en miroir
des routes `/edge/download/*` existantes. Drift vérifié localement : 0 nouvelle
route non couverte.
