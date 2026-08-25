# Feature Specification: Pack FR — lot 1 (cotisations détaillées, Fillon, PAS, DSN, golden) (#5438)

**Feature Branch**: `mod/payroll/5438-pack-fr`

**Created**: 2026-08-25

**Status**: Implementation

**Input**: Issue #5438 — Pack FR 100 % (bulletin France, Fillon, PAS, DSN). Socle existant (#5254) : SMIC 2026 (1 867,02 €), barème IR 2026, cotisations simplifiées, `GoldenFrPayrollTest` (15 tests), `FR_COMPLIANCE.md` (gaps E1/E2/E3).

## Problème (vérifié 2026-08-25)

- Cotisations : modèle agrégé (SS 7,5/30 % + CSG/CRDS) ≠ structure URSSAF réelle (maladie, vieillesse plafonnée/déplafonnée, retraite complémentaire, prévoyance, chômage, FNGS) — **gap E3**.
- Réduction **Fillon** : non implémentée.
- **PAS** : taux neutre annuel/12 uniquement — pas de taux personnalisé — **gap E2**.
- **DSN** : aucun export S21.G00 — **gap E1**.
- Bulletin PDF : générique existant (payslip.blade) ; net social FR à exposer.

## Décision (lot 1, pilot — validation expert-comptable requise avant production, #1904)

1. **Cotisations URSSAF détaillées** dans `FrancePayrollRules::socialContributions()` (codes stables, `cap` honoré par `computeContribution`) :

| Code | Libellé | Salarié | Employeur | Plafond |
|---|---|---|---|---|
| `MAL_FR` | Maladie | 0,00 | 13,00 | — |
| `VIE_PLF_FR` | Vieillesse plafonnée | 6,90 | 8,55 | PMSS |
| `VIE_DPL_FR` | Vieillesse déplafonnée | 0,40 | 1,90 | — |
| `RET_T1_FR` | Retraite complémentaire T1 | 3,15 | 4,72 | PMSS |
| `PREV_FR` | Prévoyance (pilot) | 1,50 | 1,50 | — |
| `CHO_FR` | Chômage | 0,00 | 4,05 | — |
| `FNGS_FR` | FNGS | 0,00 | 0,50 | — |
| `CSG_FR` | CSG | 9,20 | 0 | base 98,25 % |
| `CRDS_FR` | CRDS | 0,50 | 0 | base 98,25 % |

   PMSS 2026 = **4 005 €/mois** (PASS 48 060 €, +2 % — arrêté 2026, vérifié 2026-08-25). `calculateSocialCharges()` réécrit sur ces lignes (arrondi 2 déc. par ligne). Convention conservée : assiette IR = brut − cotisations salariales (documentée §4 FR_COMPLIANCE).
2. **Fillon** : `fillonReduction(float $monthlyGross)` — coefficient (T/0,6) × (1,6 × SMIC_annuel / rémunération_annuelle − 1), T = 0,3206 (pilot ≥ 20 salariés), borné [0 ; T] ; zéro au-delà de 1,6 × SMIC mensuel (2 987,23 €).
3. **PAS** : `withholdingTax(float $taxableBase, float $rate)` — taux personnalisé (défaut taux neutre = `calculateIncomeTax`).
4. **Net social** : `netSocial(float $gross, float $employeeCharges)` = brut − cotisations salariales (définition pilot, documentée).
5. **DSN** : `DsnExportService` (S21.G00 — blocs `S21.G00.01` individu, `S21.G00.06` rémunération, `S21.G00.11` cotisations) + commande `payroll:export-dsn {run}` ; XML bien formé, valeurs des blocs testées (validation URSSAF hors périmètre — documenté).
6. **Golden tests FR v2** : recalculés à la main (SMIC/Fillon max, cadre 4 000, temps partiel, PAS 8 %, net social) + mise à jour `PayrollCalculationContractTest` (FR 3 000).

## Tests (DoD)

- `GoldenFrPayrollTest` : SMIC (salarié 401,04 / employeur 638,89 / assiette 1 465,98 / IR 54,92 / net 1 411,06), cadre 4 000 (859,21 / 1 368,80 / 367,54 / 2 773,25), temps partiel 1 200 (IR 0), Fillon SMIC = 0,3206 × brut, Fillon 4 000 = 0, PAS 8 % = 117,28, net social.
- `PayrollCalculationContractTest` : valeurs FR 3 000 mises à jour (salarié 644,41 / employeur 1 026,60 / tax_base 2 355,59 / IR 152,78 / net 2 202,81 / coût 4 026,60) — recalculées à la main (vérifiées contre `PayrollCalculator::computeNetBreakdown`).
- `DsnExportServiceTest` : XML bien formé, blocs S21.G00.01/.06/.11, montants nets/cotisations, isolation par run.
- Non-régression : DZ/CI/CM/CEMAC intacts (moteur partagé inchangé).

## Hors périmètre (lot 1)

- Validation DSN contre un validateur URSSAF en CI (documenté, gap E1 partiel).
- Taux personnalisé PAS réel transmis par l'administration (interface, pas de flux).
- Versement mobilité, CET, forfait jours, accords de branche.
