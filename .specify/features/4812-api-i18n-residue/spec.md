# Feature Specification: Messages API localisés — résiduel #4690 (Closes #4812)

**Branch**: `fix/4812-api-i18n-residue` | **Created**: 2026-08-17 | **Issue**: #4812 (P2, api, i18n)

## Contexte
#4690 clôturée sans correctif ; ~15 littéraux FR/EN dans des messages API exposés (abort/json), hors catalogue errors.* ×4 locales (pattern #4395/#4396/#4292). Liste complète dans l'issue.

## Requirements
- **FR-001**: clés `errors.*` ×4 locales pour chaque message (parité `LangCatalogParityTest`).
- **FR-002**: littéraux remplacés par `__('errors.KEY')` ; `localized_message` servi.
- **FR-003**: test i18n (Accept-Language EN → message EN) sur 3 chemins représentatifs.

## Success Criteria
- **SC-001**: `grep` des littéraux → 0 dans app/ (hors lang/).
- **SC-002**: PHPStan strict vert ; `check-hardcoded-accented-messages.sh` vert.
