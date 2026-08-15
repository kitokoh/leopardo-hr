# Feature Specification: Routes manifest #2212 restaurées dans leopardo_hr (Closes #3826)

**Feature Branch**: `fix/3826-manifest-hr-routes`
**Created**: 2026-08-15 | **Status**: In progress
**Issue**: #3826 (P2, mobile, régression #3284)

## Contexte

Garde `check-mobile-manifest-routes.sh` (#2212, câblée `mobile-apps-ci.yml`) rouge sur main : le manifeste `MobileExperienceService.php` sert `/notifications`, `/evaluations`, `/history` mais `leopardo_hr/lib/app.dart` ne déclare plus ces routes depuis #3715 (Closes #3284). Tap sur ces modules → `context.push` → **crash GoRouter**.

## User Stories

### US1 — Les modules servis par le manifeste naviguent sans crash (P1)

En tant qu'utilisateur HR mobile, je veux ouvrir Notifications / Évaluations / Historique depuis l'accueil sans crash.

**Acceptance Scenarios**:
1. Given le routeur leopardo_hr, When parsing, Then `/notifications`, `/evaluations`, `/history` déclarés avec leurs écrans.
2. Given le manifeste #2212, When `check-mobile-manifest-routes.sh`, Then PASS.
3. Given le build Flutter HR, When `flutter analyze`, Then 0 erreur.

## Requirements

- **FR-001**: restaurer les 3 GoRoutes + imports dans `front/mobile_apps/leopardo_hr/lib/app.dart` (ordre du manifeste : notifications, evaluations, history).
- **FR-002**: ne PAS restaurer les 6 routes réellement mortes (modules/rh, expenses, ai-chat, ai-voice, vehicle-map, training) — absentes du manifeste.
- **FR-003**: CHANGELOG + garde verte.

## Success Criteria

- `bash dev-hub/tools/check-mobile-manifest-routes.sh` → OK.
- CI mobile verte sur la PR.
