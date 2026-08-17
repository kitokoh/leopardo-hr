# Feature Specification: Admin i18n — Settings/System vues (Closes #4815)

**Branch**: `fix/4815-admin-i18n-residue` | **Created**: 2026-08-17 | **Issue**: #4815 (P2, admin, i18n)

## Contexte
#4305/#4410 clôturées sans correctif. SettingsView (~14 littéraux FR), SystemView (+ autres vues) hors catalogue admin ×4.

## Requirements
- **FR-001**: localiser SettingsView + SystemView via catalogue admin (`settings.*`, `system.*`) ×4 locales.
- **FR-002**: garde check-i18n-diff verte ; eslint vert.

## Success Criteria
- **SC-001**: zéro littéral FR hors `$t` sur les 2 vues ; **SC-002**: parité catalogue ×4.
