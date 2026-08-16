# Feature Specification: Scanner i18n-debt — surface leopardo_hr (Closes #4397)

**Feature Branch**: `fix/4397-i18n-debt-hr-surface`
**Created**: 2026-08-16 | **Status**: Implemented
**Issue**: #4397 (P3, tooling, i18n)

## Contexte

`dev-hub/tools/i18n-debt.js` (PA2-I18N-015) scannait 6 surfaces
(mobile_employee, mobile_manager, mobile_platform_admin, web_client,
admin_dashboard, kiosk) — la 4ᵉ app tenant **`leopardo_hr`** (71 fichiers
Dart, ~200+ chaînes FR codées en dur) était absente → sa dette n'apparaissait
ni dans `docs/validation/I18N_DEBT_REPORT_*.md` ni dans #4194.

## User Stories & Testing

### User Story 1 — La dette i18n de l'app HR est mesurée (P3)

En tant que mainteneur i18n, je veux voir la dette de `leopardo_hr` dans le
rapport pour la prioriser avec les autres apps.

**Acceptance Scenarios**:
1. Given `node dev-hub/tools/i18n-debt.js`, When le scan se termine, Then une
   section `### mobile_hr` liste signals/P1/P2.
2. Given le rapport committé, When je le lis, Then les chiffres leopardo_hr
   sont visibles (676 signaux : 225 P1 / 451 P2).
3. Given les autres surfaces, When le scan rejoue, Then leurs comptes restent
   inchangés (aucune régression de détection).

## Requirements

- **FR-001**: surface `mobile_hr` ajoutée à la liste `surfaces` de
  `i18n-debt.js` (dir `front/mobile_apps/leopardo_hr/lib`, priorités
  login/account/attendance/absences/payroll/notifications/evaluations).
- **FR-002**: rapport `docs/validation/I18N_DEBT_REPORT_2026_08_16.md`
  régénéré avec la nouvelle surface.
- **FR-003**: le scanner reste un rapport (pas de gate bloquant) — la garde
  diff PA2-I18N-014 reste l'anti-régression.

## Success Criteria

- **SC-001**: `I18N_DEBT_TOTAL` = 11238 (P1 2848 / P2 8390) après ajout.
- **SC-002**: section `### mobile_hr` présente dans le rapport.
- **SC-003**: aucune modification de comportement sur les autres surfaces.
