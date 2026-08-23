# Feature Specification: Pack Turquie — audit 2026 (issue #5253)

**Feature Branch**: `mod/payroll/5253-pack-tr-2026`

**Created**: 2026-08-23

**Input**: Issue #5253 — Pack Turquie 100 % (SGK, damga vergisi, gelir vergisi, asgari ücret, golden ≥ 15).

**Audit 2026** : `TurkeyPayrollRules` contenait le barème 2024 (110 000/230 000/580 000/3 000 000) et l'asgari ücret 2024 (20 002 TRY) — OBSOLÈTES.
- Barème gelir vergisi 2026 salariés (GVK art. 103) : 0–190 000 15 % · 190 001–400 000 20 % · 400 001–1 500 000 27 % · 1 500 001–5 300 000 35 % · > 5 300 000 40 %.
- Asgari ücret : 33 030 TRY brut / 28 075,50 net (2026).

**Décisions** :
- Moteur : `minimumWage()` → 33 030 ; `defaultTaxSlabs()` → barème 2026 (constitution §III).
- Tests : `GoldenTrPayrollTest` réécrit — 15 tests / 32 assertions (3 profils + preuves charges 22,5 %/arrondi ligne + 9 bornes exactes ± 1 TRY). Valeurs calculées à la main.
- `PayrollCountryRulesTest` : borne TR → 190 000/12 = 2 375,00.
- Doc : `docs/payroll/TR_COMPLIANCE.md` réécrite (2026 + gaps E1-E3).

**Gaps documentés (non implémentés — hors périmètre session)** : E1 damga vergisi 0,759 %, E2 istisna asgari ücret (net officiel 28 075,50 vs net moteur 23 252,07), E3 tavan SGK 7,5× asgari = 247 725 (goldens limités à ≤ 240 000).

**Validation** : 15 tests verts, Pint PASS, PHPStan strict 0 erreur.
