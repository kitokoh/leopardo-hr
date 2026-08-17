# Feature Specification: errors.php — suppression des clés dupliquées (Closes #4877)

**Branch**: `fix/4877-errors-dup-keys` | **Created**: 2026-08-17 | **Issue**: #4877 (P3, api, i18n)

## Contexte
6 clés dupliquées (blocs identiques) dans `api/lang/{fr,en,tr,ar}/errors.php` suite à un merge errors.php concurrent. En PHP la dernière définition gagne → premier bloc mort, risque de divergence.

## Requirements
- **FR-001**: une seule définition par clé (`MANAGER_REQUIRED`, `INSUFFICIENT_ROLE`, `EMPLOYEE_NOT_FOUND`, `INVALID_TOKEN`, `CAMERA_NOT_FOUND`, `SELF_DISABLE_FORBIDDEN`) ×4 locales.
- **FR-002**: aucune valeur modifiée (suppression pure).

## Success Criteria
- **SC-001**: `preg_match_all` → 0 duplicat ×4 locales ; `LangCatalogParityTest` vert ; `php -l` vert.
