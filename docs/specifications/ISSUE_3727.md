# ISSUE_3727 — Scope global BelongsToCompany fail-open → requêtes cross-tenant silencieuses

**Statut**: Fixed (PR `fix/3727-belongs-to-company-fail-closed`) · **Priorité**: P3 · **Module**: Core/Tenant (security)

## Constat (audit 360° 2026-08-15, A-04)

`api/app/Shared/Traits/BelongsToCompany.php` : sans `current_company` liée au
conteneur, le scope global ne filtrait rien → toute requête HTTP hors middleware
`tenant` (login, OAuth, démo, careers, trial…) balayait **toutes les compagnies**
du schéma partagé `shared_tenants`. Fuite cross-tenant silencieuse.

## Correctif

- **Scope fail-closed en HTTP** : requête sans contexte tenant ET sans
  contrainte `company_id` explicite → `MissingTenantContextException` (403,
  code `MISSING_TENANT_CONTEXT`, `api/app/Exceptions/`). Détection de
  contrainte : `company_id`, `<table>.company_id` (relations hasMany), clauses
  Nested — les requêtes volontairement scopées par le caller restent autorisées.
- **Config** : `api/config/tenancy.php` → `fail_closed_without_context`
  (env `TENANT_FAIL_CLOSED_WITHOUT_CONTEXT`, défaut **true**).
- **Console/jobs** : comportement historique conservé + warning structuré
  (contexte via `TenantManager::withinTenant()` ou opt-out).
- **Opt-outs explicites** (accès cross-tenant volontaires) :
  - `WebAuthController::login` (login web par email)
  - `DemoLoginController` (token de démo hashé)
  - `RequestTrialSignup::findExistingManager` (anti-doublon #2678)
  - `LaunchReadinessController`, `PlatformAdminWebhookController`,
    `ActivateCompany`, `CompanyProvisioningService` (plateforme super-admin)
  → `withoutGlobalScopes('company')` + commentaire. `AuthService`/
  `PasswordResetController` utilisaient déjà l'opt-out partout.
- **Défense en profondeur** : `WebhookController::index` filtre `company_id`
  explicitement en plus du scope global.

## Tests

`api/tests/Feature/Security/TenantScopeFailClosedTest.php` (8 scénarios) :
fail-closed HTTP, code d'erreur 403/MISSING_TENANT_CONTEXT, contrainte
`company_id` explicite OK, relation hasMany OK, opt-out OK, legacy console,
config off, webhook scoped.

## Artifacts spec-kit

`.specify/features/3727-tenant-scope-fail-closed/{spec,tasks}.md`
