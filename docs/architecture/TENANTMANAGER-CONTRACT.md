# Contrat TenantManager global

> **MAT-004 (issue #5862)** — TenantManager, `current_company`, `search_path`
> et `withinTenant` couverts dans HTTP, jobs, events, cache et exports.
> Tests contractuels : `api/tests/Feature/Governance/TenantManagerContractTest.php`

## Le contrat

| Moment | Exigence |
|---|---|
| **HTTP (API tenant)** | `TenantMiddleware` résout l'employé, sa `company` et appelle `TenantManager::setTenant()` — sinon 401 (`UNAUTHENTICATED`) / 403 (`COMPANY_NOT_FOUND`). Toute requête tenant **sans contexte échoue**. |
| **Jobs / commands** | Tout traitement métier s'exécute via `TenantManager::withinTenant($company, fn)` — le contexte est restauré **en `finally`**, même sur exception. |
| **Events** | Les listeners voient le contexte du tenant courant ; le contexte du caller est restauré après le dispatch. |
| **Cache** | Clés physiques préfixées `tenant:{companyId}:{key}` (`TenantCacheService::tenantKey`) — aucune collision cross-tenant, invalidation ciblée. |
| **Exports / read models** | Toute requête sur un modèle `BelongsToCompany` hors contexte tenant échoue **fail-closed** (`TenantContextMissingException`, marqueur `tenant_scope_required` posé par le middleware) au lieu de fuir cross-tenant. |
| **Données** | `company_id` auto-rempli à la création (`BelongsToCompany::creating`), scope global `company` sur toutes les requêtes, isolation prouvée par comptage croisé. |

## Couverture de tests (TenantManagerContractTest)

1. `test_http_tenant_request_without_context_fails` — 401 sans auth.
2. `test_http_tenant_request_resolves_current_company` — 200 + `hasTenant()` + `current() == company`.
3. `test_within_tenant_restores_previous_context_and_search_path` — restauration company + `search_path` en finally, `clearTenant()` ramène au path initial.
4. `test_within_tenant_restores_context_even_on_exception` — `finally` prouvé sur exception.
5. `test_events_see_tenant_context_and_context_is_restored` — listener voit B, caller restauré sur A.
6. `test_no_cross_tenant_leak_when_switching_tenants` — écriture côté A lisible seulement côté A, zéro fuite côté B.
7. `test_cache_keys_are_tenant_scoped` — valeurs distinctes par tenant, invalidation isolée.
8. `test_tenant_scoped_query_without_context_fails_closed` — `TenantContextMissingException` hors contexte sur surface tenant.

## Règles pour les développeurs

- **Jamais** de requête métier sans contexte : utiliser `withinTenant()` (jobs),
  ou laisser `TenantMiddleware` poser le contexte (HTTP).
- **Jamais** de `SET search_path` manuel hors `TenantManager`.
- **Jamais** de clé cache sans préfixe tenant : utiliser `TenantCacheService`.
- `company_id` n'est jamais une preuve d'autorité : il est auto-rempli par le
  trait, jamais accepté depuis le payload.
- Toute nouvelle surface (route, job, export) doit ajouter son assertion au
  contrat ci-dessus si elle touche au contexte tenant.

## Non-régression

Le test est branché sur la suite Feature existante (RefreshTenantDatabase,
PostgreSQL `search_path` public + shared_tenants). Aucune modification de code
de production — ce livrable est purement contractuel (tests + documentation).
