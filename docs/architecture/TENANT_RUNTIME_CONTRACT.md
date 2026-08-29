# Contrat d'exécution tenant — routes, jobs, events, imports/exports, webhooks, cache, read models

- **Statut :** actif — standardisation du contexte tenant (issue #5736, CRM PRE)
- **Date :** 2026-08-28
- **Source de vérité code :** `App\Core\Tenant\TenantManager`
- **Conventions de verrouillage :** `docs/architecture/TENANT_CONTEXT_CONVENTIONS.md` (#5706) — HTTP/jobs/events/cache
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`

> Ce document est le **contrat unique** d'établissement et de restauration du
> contexte tenant pour toutes les surfaces d'exécution du monorepo. Tout
> nouveau code tenant (dont le CRM client) doit suivre ce contrat ; toute
> évolution de `TenantManager` doit maintenir ces invariants.

---

## 1. API canonique du contexte tenant

| Méthode | Rôle | Garantie |
|---|---|---|
| `TenantManager::setTenant(Company $c)` | Établit le tenant : `SET search_path TO "<schema>",public` + binding `current_company` | Mémorise l'état précédent (`previousPath`, `previousCompany`) |
| `TenantManager::resetToPrevious()` | Restaure l'état précédent (search_path + `current_company`) | Tolère `25P02` (transaction avortée) sans masquer l'exception d'origine |
| `TenantManager::withinTenant(Company $c, Closure $cb)` | Exécute `$cb` dans le tenant puis restaure | **`finally` : restauration garantie succès OU exception** ; restaure vers l'état intermédiaire en cas de scopes imbriqués |
| `TenantManager::current()` / `hasTenant()` | Lecture du tenant actif | `current()` → `?Company` ; `hasTenant()` → bool |
| `TenantManager::clearTenant()` | Désactive le contexte (commands/tests) | `search_path` → `public` + `current_company` retiré |
| `currentCompany()` (helpers) | Lecture globale | Ne PAS appeler sans contexte actif (lève/retourne non-typé) — préférer `TenantManager::current()` |
| `TenantScopedJob` + `EnsureTenantContext` | Contrat jobs | `tenantCompanyId(): ?string` ; middleware établit le contexte avant `handle()` et restaure après |
| `TenantContextGuard::assertHasTenant()` | Garde fail-closed programmatique | Lève `TenantContextMissingException` sans tenant actif |
| `TenantCacheService` | Cache tenant-scopé | Clé `tenant:{companyId}:{key}` — jamais de clé sans tenant |

Invariants non négociables :

1. **Jamais de `company_id` client comme autorisation.** Le tenant provient du
   contexte authentifié (HTTP) ou du contrat explicite (job/event), jamais d'un
   champ de payload non authentifié.
2. **Toujours restaurer.** Toute surface qui établit un contexte tenant doit le
   restaurer en `finally` (ou via middleware) après succès **et** exception.
3. **Fail-closed.** Une opération déclarée tenant-scopée sans tenant actif
   échoue immédiatement (`TenantContextMissingException`) — jamais d'exécution
   non scopée silencieuse.
4. **Les clés (cache, événements, imports/exports, read models) contiennent le
   tenant.** Un identifiant ou artefact sans tenant est un défaut de contrat.

## 2. Matrice par surface

| Surface | Établir | Restaurer | Garde fail-closed | Référence |
|---|---|---|---|---|
| **HTTP (routes tenant)** | `TenantMiddleware` (auth → `setTenant`) | `finally { resetToPrevious() }` | `tenant_scope_required` → `TenantContextMissingException` ; cross-tenant → **404** (jamais 403) | `TenantMiddleware`, `BelongsToCompany` (#3727) |
| **Jobs** | `TenantScopedJob::tenantCompanyId()` + `EnsureTenantContext` (middleware) | après `handle()` (middleware) | compagnie absente → log + `release(30)` ; job sans tenant qui touche des données tenant → rejet avant accès | `EnsureTenantContext` (#5736) |
| **Events** | dispatch dans `withinTenant()` ou via `TenantEventDispatcher` (contrat `TenantScopedEvent`) | par le dispatcher/scope | événement tenant sans contexte → fail-closed | #5706 (en cours) |
| **Imports** | contexte tenant posé AVANT l'import (job `TenantScopedJob` ou `withinTenant`) | `finally` | import sans tenant rejeté ; artefacts (fichiers, traces) préfixés du tenant | #5714 (CRM V0) |
| **Exports** | génération dans le contexte du tenant demandeur ; artefacts nommés `{tenant}-{type}-{période}` | `finally` | export cross-tenant impossible (404) ; fichiers expirants et audités | #5729 (CRM V1) |
| **Webhooks (inbound)** | résolution du tenant UNIQUEMENT depuis signature/HMAC authentifiée (jamais d'un champ non signé) | `finally` | signature invalide, replay, payload hors bornes → rejet avant traitement | #5740 (CRM PRE) |
| **Webhooks (outbound)** | payloads versionnés avec `tenant_id` + `correlation_id` | — | clés de cache/outbox tenant-scopées | #5741/#5744 (CRM PRE) |
| **Cache** | `TenantCacheService::remember/put` (clé `tenant:{id}:{key}`) | invalidation ciblée par tenant | jamais de clé générique partagée entre tenants | `TenantCacheService` |
| **Read models** | lecture/agrégation dans `withinTenant()` ; clé d'idempotence = `{tenant}:{type}:{période}` | `finally` | recalcule sans tenant rejeté | #5729 (CRM V1) |
| **Tests** | helpers `withinTenant` / fixtures double-tenant | teardown `resetTestSearchPath` + `forgetInstance('current_company')` | assertions cross-tenant (404, clés distinctes) obligatoires | `Tests\TestCase`, #5738 (CRM PRE) |

## 3. Contrat des jobs (détaillé)

Un job qui touche des modèles tenant DOIT :

```php
final class MonJob implements ShouldQueue, TenantScopedJob
{
    public function __construct(private readonly string $companyId) {}

    public function tenantCompanyId(): ?string { return $this->companyId; }

    public function middleware(): array { return [new EnsureTenantContext]; }
}
```

- `tenantCompanyId() === null` **et** accès à des données tenant = rejeté avant
  accès (fail-closed) — testé dans `TenantManagerStandardizationTest`.
- Compagnie introuvable = `release(30)` + log `queue.tenant_context.company_not_found`
  (pas de `fail` : réplication/race possible).
- Le middleware restaure le contexte APRÈS `handle()`, même en cas d'exception
  dans le job (le `withinTenant` interne fait `finally`).

## 4. Contrat des événements

- Tout événement porteur de données tenant implémente `TenantScopedEvent`
  (`tenantCompanyId(): ?string`) et est dispatché via le `TenantEventDispatcher`
  ou dans un `withinTenant()` explicite (verrouillage #5706).
- Chaque événement expose `tenant_id` + `correlation_id` (convention #1874) dans
  son payload sérialisé.

## 5. Anciennes références

- `AbstractTenantModel` : **supprimé des instructions CRM** (issue #5736) — le
  scope canonique est le trait `App\Shared\Traits\BelongsToCompany` (doublon
  legacy `App\Traits\BelongsToCompany` à résorber). Ne pas réintroduire la
  classe historique dans les specs/modules CRM.

## 6. Tests de référence

- `api/tests/Feature/Tenant/TenantManagerStandardizationTest.php` — #5736 :
  restauration succès/exception/imbriqué, rejet job sans tenant, clés cache,
  isolation deux tenants.
- `api/tests/Feature/TenantContextLockdownTest.php` — #5706 : HTTP cross-tenant
  404, jobs, events, garde générique.
- `api/tests/Feature/CrossTenantIndexIsolationTest.php` — #3231 : contrôleurs
  sensibles re-scopés `where('company_id', ...)` en tête.
