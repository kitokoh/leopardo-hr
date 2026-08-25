# Feature Specification: Pack France — audit 2026 (issue #5254)

**Feature Branch**: `mod/payroll/5254-pack-fr-2026`

**Created**: 2026-08-23

**Input**: Issue #5254 — Pack France 100 % (URSSAF, PAS, export DSN, golden ≥ 15).

**Audit 2026** : `FrancePayrollRules` contenait le barème IR 2025 (11 294/28 797/82 341/177 106) et le SMIC 2024 (1 766 €) — OBSOLÈTES.
- Barème IR 2026 (LF 2026, +0,9 %) : 0–11 600 € 0 % · 11 601–29 579 € 11 % · 29 580–84 577 € 30 % · 84 578–181 917 € 41 % · > 181 917 € 45 %.
- SMIC : 1 867,02 €/mois depuis le 1er juin 2026 (+2,41 % ; 12,31 €/h × 151,67 h).

**Décisions** :
- Moteur : `minimumWage()` → 1 867,02 ; `defaultTaxSlabs()` → barème 2026 (constitution §III : docs + goldens + CHANGELOG en même temps).
- Tests : `GoldenFrPayrollTest` réécrit — 15 tests / 32 assertions (3 profils SMIC/cadre/haut + 2 profils intermédiaires + 9 bornes exactes du barème ± 1 € + preuve assiette CSG/CRDS 98,25 %). Chaque valeur calculée à la main.
- `PayrollCountryRulesTest` : bornes FR → 11 600/11 601.
- Doc : `docs/payroll/FR_COMPLIANCE.md` réécrite (2026 + gaps E1-E3).

**Gaps documentés (non implémentés — hors périmètre session)** : E1 export DSN (structure minimale), E2 PAS taux personnalisé (modélisé taux neutre annuel/12), E3 cotisations URSSAF agrégées 7,5/30 % (pilot — structure réelle ~20 lignes).

**Validation** : 15 tests verts, Pint PASS, PHPStan strict 0 erreur.
