# Mini-spec — Issue #3908

## Problème

`edge/docker-compose.yml` : healthcheck `curl http://localhost/api/edge/health`
— endpoint inexistant (l'Edge API sert `GET /api/v1/edge/health`) → le
conteneur edge-api est toujours « unhealthy » sur les installs clients.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| URL du healthcheck | `GET /api/v1/edge/health` (endpoint réel, EdgeController) |
| Interval/timeout/retries | Inchangés |

## Correctif

URL corrigée dans `edge/docker-compose.yml` avec commentaire.

Closes #3908
