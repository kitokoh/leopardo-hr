# Feature Specification: QA Expert #3 — Mobile (front/mobile_apps) (2026-08-15)

**Feature**: `qa-expert3-mobile-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress

## Findings traités

### #3047 [P2] — notifications PUT → 405 — **CONFIRMÉ LIVE, CORRIGÉ** (PR #3256)
> Live : `PUT /notifications/24` → 405. `leopardo_employee` utilisait `PUT /notifications/{id}/read` + `PUT /notifications/read-all` ; backend expose `PATCH …/read` + `POST …/mark-all-read`. Aligné sur manager/hr.

### #3048 [P2] — POST /user/company-requests avec retries → doublons — **CORRIGÉ** (PR #3256)
> `maxRetriesOverride: 0` ajouté dans les 3 apps.

### #3052 [P3] — cast `data['data']['id'] as String` (backend renvoie int) → TypeError — **CORRIGÉ** (PR #3256)
> `attendance_offline_service` : `(id as num).toString()`.

## Décisions
- #3053 ThemeMode.dark : par conception (PA2-MOB-012) — close avec commentaire.

## Restants (déjà filed)
- #3049 (route doublée — couvert par branch fix/3003-3004), #3050/#3051/#3054 (hygiène).
