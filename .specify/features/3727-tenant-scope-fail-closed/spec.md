# Feature Specification: Scope BelongsToCompany fail-closed (Closes #3727)

**Feature Branch**: `fix/3727-tenant-scope-fail-closed`
**Created**: 2026-08-15 | **Status**: Draft → In progress
**Issue**: #3727 (T004, P3, api, security)

## Contexte

Le scope global `BelongsToCompany` (trait canonique
`api/app/Shared/Traits/BelongsToCompany.php`, utilisé par 70+ modèles via le
re-export `App\Traits\BelongsToCompany`) est **fail-open** : sans `current_company`
liée au conteneur, le scope ne fait rien → la requête s'exécute sur TOUTES les
compagnies du schéma partagé (`shared_tenants`) → **fuite cross-tenant
silencieuse**. Les endpoints publics (login, OAuth, démo, careers, trial…)
n'ont pas de middleware `tenant` et sont donc concernés.

## User Stories & Testing

### User Story 1 — Échec explicite au lieu de fuite silencieuse (P1)

En tant qu'exploitant, je veux qu'une requête tenant sans contexte HTTP échoue
explicitement plutôt que de lire les données de toutes les compagnies.

**Acceptance Scenarios**:
1. Given une requête HTTP sur `Employee` sans `current_company` liée ET sans
   contrainte `company_id`, When exécution, Then `TenantContextMissingException`.
2. Given une requête HTTP scopée explicitement (`where('company_id', $id)`),
   When exécution, Then la requête s'exécute normalement.
3. Given une requête relation (`hasMany`, `with('relation')`), When exécution,
   Then la contrainte de clé étrangère `*.company_id` est reconnue → OK.
4. Given un contexte console/job/command, When exécution, Then comportement
   historique conservé (+ warning optionnel `tenant.scope.missing_context`).
5. Given `config('tenancy.fail_closed_without_context')=false`, When exécution,
   Then comportement historique (fail-open) restauré.

### Edge Cases

- Lookups pré-tenant volontaires (login email, démo token, trial findExistingManager) :
  opt-out explicite `withoutGlobalScopes()` avec commentaire #3727.
- `withoutGlobalScopes()` ne déclenche jamais le scope → aucun impact AuthService
  (pattern déjà utilisé partout en pré-tenant).
- `creating` hook inchangé (auto-fill silencieux quand contexte présent).
- `php artisan` / jobs queue (SAPI cli) : garde inactive (legacy) ; production
  HTTP php-fpm : garde active.

## Requirements

### Functional Requirements

- **FR-001**: le scope DOIT lever `TenantContextMissingException` en HTTP sans
  contexte tenant et sans contrainte `company_id` explicite.
- **FR-002**: la détection de contrainte DOIT reconnaître `company_id`,
  `<table>.company_id` (relations) et les clauses Nested.
- **FR-003**: le comportement console/jobs DOIT rester inchangé (compat).
- **FR-004**: un flag de config `tenancy.fail_closed_without_context` (env
  `TENANT_FAIL_CLOSED_WITHOUT_CONTEXT`, défaut true) DOIT permettre le retour
  au fail-open.
- **FR-005**: les 4 sites de lookup pré-tenant volontaires DOIVENT porter un
  opt-out explicite documenté.

## Success Criteria

- **SC-001**: `TenantScopeFailClosedTest` (4 scénarios) vert en CI.
- **SC-002**: PHPStan strict (level 8) vert ; Pint propre.
- **SC-003**: aucune régression sur auth (login/OAuth/reset), careers public,
  trial signup, kiosk — suites de tests existantes vertes.

## Assumptions

- Runtime production = php-fpm (pas d'Octane/RoadRunner — vérifié
  composer.json) → `runningInConsole()` est fiable pour distinguer HTTP/CLI.
- Les endpoints tenants API passent tous par le middleware `tenant`
  (`TenantMiddleware` → `TenantManager::setTenant`).
