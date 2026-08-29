# Contexte Tenant — Rapport des conventions réelles et verrouillage

- **Statut :** actif — cartographie et verrouillage (issue #5706, CRM-V0-02)
- **Date :** 2026-08-28
- **Périmètre :** `api/app/Core/Tenant/`, `api/app/Http/Middleware/`, jobs, events, cache
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md` (ADR-CRM-003)

> Ce rapport décrit **ce qui existe réellement** dans le code (vérifié sur
> `main` au 2026-08-28), puis les verrouillages ajoutés par cette issue.
> Il sert de source de vérité pour tout nouveau module tenant (dont le CRM).

---

## 1. Chaîne de résolution du tenant (requêtes HTTP)

| Étape | Mécanisme | Fichier |
|---|---|---|
| 1. Authentification | `$request->user()` via Sanctum (`Employee`) | `TenantMiddleware` |
| 2. Résolution compagnie | `$employee->company`, repli `public.user_lookups` (email → schéma) ou abilities du token (`tenant_schema:`, `tenant_email:`, `tenant_company:`, `tenant_employee:`) | `TenantMiddleware::hydrateTenantEmployee()` |
| 3. Gardes d'état | statut compagnie (`suspended`/`expired`) → 403 `ACCOUNT_SUSPENDED` ; statut employé (`archived`/`suspended`) → 403 | `TenantMiddleware` |
| 4. Fail-closed sans compagnie | employé `ordinary` sans compagnie → marqueur `tenant_scope_required` → tout modèle `BelongsToCompany` lève 403 `TENANT_CONTEXT_MISSING` | `TenantMiddleware` + `BelongsToCompany` |
| 5. Activation du contexte | `TenantManager::setTenant($company)` : `SET search_path TO "schema",public` + binding conteneur `current_company` | `TenantManager` |
| 6. Teardown | `TenantManager::resetToPrevious()` en `finally` (search_path + binding restaurés) | `TenantMiddleware` |

Middlewares adjacents : `EnsureKioskSearchPathReset`, `AuthenticateZktecoDevice`
(voie kiosque, `search_path` explicite).

## 2. `search_path` PostgreSQL

- Toute donnée tenant vit dans le **schéma de la compagnie** (`schema_name`,
  défaut `shared_tenants`) ; le schéma `public` porte les tables plateforme.
- `TenantManager::setTenant()` exécute `SET search_path TO "schema",public` ;
  `withinTenant()` restaure l'état précédent en `finally` (y compris 25P02 en
  transaction).
- `Company::getSafeSearchPath()` sanitise le nom de schéma
  (`[^a-zA-Z0-9_]` supprimé, fallback `shared_tenants`).
- En mode **tenancy partagé** (défaut), le `search_path` est `shared_tenants,public`
  et l'isolation est logique via `company_id` ; le mode **schéma** (isolation
  physique) est verrouillé à la création (`booted` → 422 `COMPANY_SCHEMA_MODE_LOCKED`).

## 3. Scope Eloquent — `BelongsToCompany`

- Trait : `api/app/Shared/Traits/BelongsToCompany.php` (doublon legacy
  `api/app/Traits/BelongsToCompany.php` — à résorber).
- **Global scope** : toute requête filtre `company_id = current_company.id` ;
  sans `current_company` sur la surface API tenant (`tenant_scope_required`
  posé) → `TenantContextMissingException` (403).
- **Creating hook** : auto-injection de `company_id` à la création (même
  fail-closed).
- Hors surface tenant (console, jobs, routes publiques, super-admin), le
  scope est sauté silencieusement — les traversées de tenants doivent être
  explicites via `withoutGlobalScopes()`.

## 4. Identifiants compagnie

- `company_id` : **uuid non nullable**, indexé, sur toute table tenant
  (constitution §II) ; les migrations tenant vivent dans
  `api/database/migrations/tenant/`.
- Convention de requête des contrôleurs sensibles : `where('company_id',
  $actor->company_id)` **explicite en tête** (cf. `CrossTenantIndexIsolationTest`,
  #3231) — la ressource d'un autre tenant retourne **404**.

## 5. Route bindings

- Le binding de route n'est **jamais** utilisé seul : le contrôleur re-scope
  par `company_id` (ou le modèle est `BelongsToCompany`). Cross-tenant = 404
  (jamais 403, pour ne pas révéler l'existence de la ressource).

## 6. Jobs (verrouillé)

- Contrat : `App\Contracts\Queue\TenantScopedJob` (`tenantCompanyId(): ?string`).
- Middleware : `App\Jobs\Middleware\EnsureTenantContext` — établit
  `search_path` + `current_company` via `withinTenant()` avant `handle()`,
  restaure après. Compagnie absente → log + `release(30)` (pas de fail).
- **Garde** : tout job touchant des modèles tenant DOIT implémenter
  `TenantScopedJob` et déclarer `EnsureTenantContext` dans `middleware()`.
  (Ce qui manquait : un test de verrouillage explicite — ajouté par #5706.)

## 7. Events (verrouillé par #5706)

- État antérieur : les listeners tenant utilisaient `withinTenant()` de façon
  ad hoc (`ProvisionChartOfAccounts`, `ProvisionAccountingSettings`, …).
- **Nouveau contrat** : `App\Core\Tenant\Domain\Contracts\TenantScopedEvent`
  (`tenantCompanyId(): ?string`).
- **Nouveau dispatcher** : `App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher`
  — `dispatch(TenantScopedEvent $event, ?Company $company = null)` : résout le
  tenant (explicite ou courant), **fail-closed** (`TenantContextMissingException`
  si aucun tenant), exécute `event()` dans `withinTenant()`.
- Règle : tout event métier tenant-scoped doit implémenter `TenantScopedEvent`
  et être dispatché via `TenantEventDispatcher` (ou dans un `withinTenant()`
  explicite).

## 8. Cache (verrouillé)

- Service : `App\Core\Tenant\Infrastructure\Services\TenantCacheService` —
  **clé unique** `tenant:<companyId>:<key>` ; `companyId` requis par signature
  (fail-closed par construction).
- Règle : tout cache métier passe par `TenantCacheService` ou porte
  explicitement le préfixe `tenant:<companyId>:` (Upstash/Redis, pas de
  pattern-delete portable).
- Invalidation ciblée : `forget`/`forgetMany`/`flush(companyId)` (tags si
  supportés).

## 9. Verrouillages ajoutés par #5706

| Verrouillage | Mécanisme | Preuve |
|---|---|---|
| Jobs tenant-obligatoires | `TenantScopedJob` + `EnsureTenantContext` (existant) | `TenantContextLockdownTest` |
| Events tenant-obligatoires | `TenantScopedEvent` + `TenantEventDispatcher` (nouveau) | `TenantContextLockdownTest` |
| Cache tenant-scopé | `TenantCacheService` (existant) | `TenantCacheServiceTest` + `TenantContextLockdownTest` |
| Accès cross-tenant → 404 | scope explicite + tests | `TenantContextLockdownTest::test_cross_tenant_leave_balance_access_returns_404` |
| Garde générique fail-closed | `TenantContextGuard::assertHasTenant()` (nouveau) | `TenantContextLockdownTest` |

## 10. Application au module CRM (issues #5705+)

- Toute table `crm_*` : `company_id` uuid non nullable + index + modèle
  `BelongsToCompany` (voir `MODULE_CRM_INTERNE_CLIENT.md`).
- Tout endpoint `/api/v1/crm/*` : scope explicite + 404 cross-tenant testé.
- Tout job/event/cache CRM : contrats ci-dessus.
