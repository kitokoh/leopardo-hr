# Feature Specification: EnsureEmployeeMiddleware localisé (Closes #4878)

**Branch**: `fix/4878-middleware-fr-literals` | **Created**: 2026-08-17 | **Issue**: #4878 (P2, api, i18n)

## Contexte
`EnsureEmployeeMiddleware` expose 2 littéraux FR hors catalogue : `Compte inactif.` / `Societe suspendue ou expiree.` — résiduel #4812.

## Requirements
- **FR-001**: clés `errors.EMPLOYEE_INACTIVE` + `errors.COMPANY_SUSPENDED_EXPIRED` ×4 locales.
- **FR-002**: littéraux remplacés par `__()` (locale SetLocale).
- **FR-003**: test i18n (Accept-Language EN → message EN).

## Success Criteria
- **SC-001**: `grep` littéraux → 0 ; `LangCatalogParityTest` vert ; test EN vert ; PHPStan strict.
