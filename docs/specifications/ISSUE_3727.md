# ISSUE_3727 — Scope global BelongsToCompany fail-open → requêtes cross-tenant silencieuses

**Statut**: Fixed (PR `fix/3727-tenant-scope-fail-closed`) · **Priorité**: P3 · **Module**: Core/Tenant (security)

## Constat (audit 360° 2026-08-15, A-04)

`api/app/Shared/Traits/BelongsToCompany.php` : sans `current_company` liée au
conteneur, le scope global ne filtre rien → toute requête HTTP hors middleware
`tenant` (login, OAuth, démo, careers, trial…) balaie **toutes les compagnies**
du schéma partagé `shared_tenants`. Fuite cross-tenant silencieuse.

## Correctif

- **Scope fail-closed en HTTP** : requête sans contexte tenant ET sans
  contrainte `company_id` explicite → `TenantContextMissingException`
  (`api/app/Core/Tenant/Domain/Exceptions/`). Détection de contrainte :
  `company_id`, `<table>.company_id` (relations hasMany), clauses Nested.
- **Config** : `api/config/tenancy.php` → `fail_closed_without_context`
  (env `TENANT_FAIL_CLOSED_WITHOUT_CONTEXT`, défaut **true**) ;
  `log_missing_tenant_context` pour tracer la dérive console.
- **Console/jobs** : comportement historique conservé (les jobs DOIVENT passer
  par `TenantManager::withinTenant()`).
- **Opt-outs explicites pré-tenant** (lookups volontairement cross-tenant) :
  - `WebAuthController::login` (login web par email)
  - `DemoLoginController` (token de démo hashé)
  - `RequestTrialSignup::findExistingManager` (anti-doublon #2678)
  → `withoutGlobalScopes()` + commentaire #3727. AuthService/PasswordReset
  utilisaient déjà `withoutGlobalScopes()` partout.

## Tests

`api/tests/Feature/Tenancy/TenantScopeFailClosedTest.php` (4 scénarios) :
fail-closed HTTP, contrainte explicite OK, legacy console, config off.

## Artifacts spec-kit

`.specify/features/3727-tenant-scope-fail-closed/{spec,tasks}.md`
