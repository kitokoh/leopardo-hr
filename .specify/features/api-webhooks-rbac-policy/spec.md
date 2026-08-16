# Feature Specification: RBAC webhooks unifié via Policy (issue #3949)

**Feature Branch**: `fix/3949-webhooks-rbac`
**Created**: 2026-08-16
**Status**: Implemented — PHPUnit 5/5 + régression SSRF 8/8, Pint, PHPStan strict OK

## Problème

`WebhookController` gardait `index/show/destroy/test` en contrôleur (`hasManagerRole('principal')`) alors que `store/update` reposaient sur `authorize()` des FormRequests — surface éclatée, aucune assertion niveau contrôleur : une refactor du FormRequest rouvrirait la surface sans test.

## Solution

- `App\Policies\WebhookEndpointPolicy` : règle unique `manage()` (principal) + `view` incluant le scope tenant (cross-tenant → deny). Enregistrée via `Gate::policy` dans `AppServiceProvider`.
- Contrôleur : `$this->authorize(...)` sur les 6 méthodes (viewAny/create/view/update/delete/test) — plus de garde inline.
- FormRequests conservés (défense en profondeur, délèguent la même règle).

## User Stories & Testing

### US1 — Garde unifiée (P1)
**Acceptance Scenarios**:
1. Given un manager `rh` (non-principal), When store/update/destroy/test, Then 403 (5 tests).
2. Given un manager `principal`, When store, Then 201.
3. Given un endpoint d'un autre tenant, When show/update/delete/test, Then deny (404/403 via policy).
