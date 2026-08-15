# Feature Specification: Bloc compliance paie exposé aux mobiles (backend)

**Feature Branch**: `feat/2144-mobile-summary-compliance`
**Created**: 2026-08-15 | **Status**: Draft → Implemented (volet backend)
**Issue**: #2144 (volet backend ; volet UI mobile → issue de suivi dédiée)

## Contexte

Le bloc `compliance` (niveau de confiance paie + avertissement localisé +
source + verification_date) est exposé par la simulation admin/web mais
absent des endpoints mobiles : `/me/balance` (employee) et
`/payroll/mobile-summary` (manager). L'écran paie mobile ne peut donc pas
afficher l'avertissement de conformité.

## User Stories & Testing

### User Story 1 — compliance sur /me/balance (P1)
**Acceptance Scenarios**:
1. Given un employé d'une entreprise DZ, When GET /me/balance,
   Then `data.compliance.level = pilot` avec warning/warning_key/source/verification_date.
2. Given un pays non résoluble, When GET /me/balance, Then `compliance = []`
   (fail-open, jamais de 500).

### User Story 2 — compliance sur /payroll/mobile-summary (P1)
**Acceptance Scenarios**:
1. Given un manager d'une entreprise MA, When GET /payroll/mobile-summary,
   Then `data.compliance.level = pilot`.

## Contraintes

- Même shape que `PayrollCalculationPresenter` (level/warning/warning_key/
  source/verification_date) — le client mobile réutilise le contrat #1872.
- Résolution du pays via `currentCompany()` (middleware tenant) avec fallback
  `public.companies` ; fail-open sur erreur de résolution des règles.
- Le volet UI mobile (bandeau localisé employee/manager) nécessite la
  régénération des fichiers l10n générés (`flutter gen-l10n`, clés
  `payrollConfidence*` déjà présentes dans les ARB) — issue de suivi.
