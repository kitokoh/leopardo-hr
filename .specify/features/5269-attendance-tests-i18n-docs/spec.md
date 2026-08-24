# Feature Specification: Pointage 100 % — tests, i18n ×4, docs (issue #5269)

**Feature Branch**: `mod/attendance/5269-tests-i18n-docs`
**Created**: 2026-08-23 · **Status**: Implémentée (PR en cours)

## 1. Contexte
Qualité du pointage : tests par parcours (modes, corrections, HS, rapports),
0 chaîne hardcodée, guide utilisateur. ADR fusion #5264 mergée (plus de blocage).

## 2. Inventaire (main 2026-08-23)
- 52 tests Feature Attendance existants (check-in/out, multi-punch, corrections
  RBAC+tenant, géo 12 tests, rapports mensuels, anomalies, timezone, auto-close).
- 5 littéraux de messages API utilisateur (ApprovalController ×2,
  CalendarSyncController, GeoAttendanceController ×2) ; 2 codes machine
  (INVALID_QR_TOKEN, EMPLOYEE_NOT_FOUND) = contrats API, exclus.
- Aucun guide utilisateur pointage.

## 3. Changements
1. **i18n ×4** : 5 messages → `attendance.*` (×4) ; valeurs EN = littéraux
   historiques (clients stables) ; `AttendanceI18nTest` (parité + contenu).
2. **Tests rapports par période** (moteur #5268 mergé) : `AttendancePeriodReportsTest`
   — weekly avec filtre employé, daily, export CSV, RBAC employé 403.
3. **Guide utilisateur** `docs/user-guide/POINTAGE.md` (modes, corrections,
   rapports, bonnes pratiques paie).

## 4. DoD
- [x] Tests Feature par parcours ajoutés (rapports day/week, RBAC).
- [x] 0 chaîne hardcodée utilisateur dans les messages pointage (scan : les
  littéraux restants sont des codes machine documentés).
- [x] i18n parité ×4 (test dédié).
- [x] Guide utilisateur + CHANGELOG.
- Coverage module : mesure documentée ; le gate global CI reste la référence.
