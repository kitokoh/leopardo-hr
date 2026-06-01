# Smoke observabilite lancement - 2026-06-01

## Contexte

Validation Render du lot 69.6 apres correction #691 sur `GET /api/v1/launch-readiness`.

- API : `https://gestionemployerbackend.onrender.com/api/v1`
- Persona : manager demo `ahmed.benali@techcorp-algerie.dz`
- Branche source : `main`

## Smoke technique

| Signal | Resultat |
|---|---|
| `GET /health` | OK - `status=ok` |
| `GET /health/live` | OK - `status=ok` |
| `GET /health/ready` | OK - `status=ok` |
| `GET /dashboard/manager-digest` | OK - payload operationnel avec `team_scope`, `team_size`, presences et actions |
| `GET /launch-readiness` | OK apres #691 |

## Readiness lancement tenant

| Indicateur | Valeur |
|---|---|
| Score | `43` |
| Go-live ready | `false` |
| Bloqueurs requis | `1` |
| Bloqueur | `communication_governance` |
| Detail bloqueur | `preferences_configured=1`, `communication_failures_7d=0` |

## Interpretation

Le cockpit technique est exploitable : health, ready, digest manager et readiness repondent sans 500/404.

Le verdict lancement TechCorp reste **Go conditionnel / No-go marketing large** tant que les preferences de communication ne sont pas initialisees pour tous les utilisateurs actifs. Ce signal est fonctionnel, pas une panne API.

## Actions suivantes recommandees

- Backfiller les `notification_preferences` manquantes pour les demos et nouveaux tenants.
- Ajouter un smoke post-seed qui compare `preferences_configured` au nombre d'employes actifs.
- Continuer a surveiller les workflows post-merge : deploy, E2E staging, OWASP et distribution mobile.
