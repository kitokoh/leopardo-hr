# Guard routes & Policies platform/tenant

> **MAT-003 (issue #5861)** — Une route platform ne doit pas être exposée dans
> l'espace tenant et inversement.
> Registre déclaratif : [`dev-hub/governance/route-owners.json`](../../dev-hub/governance/route-owners.json)
> Garde CI : [`dev-hub/tools/check-route-owner-guard.sh`](../../dev-hub/tools/check-route-owner-guard.sh)

## Classification

Le propriétaire d'une route est déterminé par ses middlewares résolus
(`php artisan route:list --json`) :

| Scope | Signal | Espace |
|---|---|---|
| `platform` | `Authenticate:super_admin_api` | Super-admin plateforme |
| `tenant` | `TenantMiddleware` | Employé authentifié d'un tenant (contexte `search_path`) |
| `public` | aucun des deux | Auth, health, webhooks providers, callbacks |

## Règles (couche route:list — bloquantes)

1. **Une route platform hors prefixes déclarés** (`/api/v1/platform`,
   `/api/v1/admin`) → échec, sauf exception déclarée
   (`platform_controller_exceptions`, ex. `/api/v1/metrics`).
2. **Un contrôleur `App\Modules\Platform\` servi dans une route tenant** →
   échec, sauf exception déclarée (`tenant_controller_exceptions`).
3. **Une route tenant servie sous un prefix platform** (inverse) → échec.

Règle complémentaire (warning, non bloquante) : route tenant API avec un
contrôleur nommé `Platform*`/`*Admin*` non déclaré — signal de revue.

## Règles (couche statique — bloquantes)

4. Tout fichier `api/routes/modules/*.php` doit être déclaré dans
   `route-owners.json` (et inversement).
5. Aucun contrôleur `App\Modules\Platform\` référencé dans un fichier de
   routes tenant, sauf exception déclarée.

## Exceptions documentées (baseline main 2026-08-28, 913 routes)

- **platform hors prefixes** : `MetricsController` (`/api/v1/metrics`, sonde
  platform historique).
- **Modules\Platform servi en tenant** : `AIWorkflowController` (workflows IA
  tenant, à migrer vers BC-23 AI), `ClientEventController`,
  `CommunicationAnalyticsController`, `LaunchReadinessController`,
  `SupportTicketController` — surfaces tenant assumées, colocalisées dans le
  module Platform.

## Tests

- Guard : `node --test dev-hub/tools/tests/check-route-owner-guard.test.mjs`
  (couche statique : registre valide, contrôleur platform non déclaré,
  fichier non déclaré, scope invalide).
- HTTP (PHPUnit) : `api/tests/Feature/Governance/RouteOwnerGuardTest.php` —
  tenant → surface platform = 401, pas d'alias tenant vers le platform (404),
  route tenant sans contexte = 401 `UNAUTHENTICATED`, cross-tenant = 404.

## Mise à jour

- Nouveau prefix platform : ajouter à `platform_uri_prefixes`.
- Nouvelle exception justifiée : ajouter `{controller, reason}` dans la liste
  appropriée — une exception sans raison est un signal de dette, pas un droit.
