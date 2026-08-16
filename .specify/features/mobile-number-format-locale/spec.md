# Feature Specification: Salaires/dates mobiles formatés selon la locale (issue #3957)

**Feature Branch**: `fix/3957-payroll-number-format-locale`
**Created**: 2026-08-16
**Status**: Implemented — validation CI mobile (flutter analyze) en cours

## Problème

`NumberFormat.decimalPattern('fr')` codé en dur sur 6 sites des écrans paie
(employee/manager/hr) : les utilisateurs AR/TR/EN voient des nombres au format
français. `DateFormat('dd/MM/yyyy')` locale-indépendant sur l'écran Smart
Attendance. Le codebase a déjà le helper `deviceIntlDateLocale` (#3405).

## User Stories & Testing

### US1 — Nombres selon la locale active (P1)
**Acceptance Scenarios**:
1. Given l'app en locale EN, When affichage du salaire net, Then `123,456.78` (pas `123 456,78`).
2. Given l'app en locale AR/TR/EN, When soldes paie, Then format décimal de la locale.

### US2 — Dates selon la locale (P2)
**Acceptance Scenarios**:
1. Given l'écran Smart Attendance, When date de session, Then format via `deviceIntlDateLocale`.
