# Conformité paie — Côte d'Ivoire (CI_COMPLIANCE.md)

> Issue #1825 — passage de `placeholder` à `pilot` pour la Côte d'Ivoire
> (membre CEDEAO/UEMOA, classe `CedeaoPayrollRules`).
>
> **Statut : PILOT** — valeurs issues du CGI ivoirien 2024 et du Code du
> travail. À valider par un expert-comptable OHADA-CI avant production
> (taux, seuils et plafonds peuvent évoluer avec les lois de finances).

## 1. ITSAS — Impôt sur les Traitements, Salaires et Assimilés (CGI art. 116-120)

- **Assiette mensuelle** : brut − CNSS salariale − **abattement frais
  professionnels 20 % du brut, non plafonné** (CGI art. 118).
- **Tranches annuelles** (assiette × 12) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 600 000 | 0 % |
| 600 001 – 2 000 000 | 2 % |
| 2 000 001 – 5 000 000 | 21 % |
| 5 000 001 – 10 000 000 | 24,5 % |
| > 10 000 000 | 29 % |

- ITSAS mensuel = impôt progressif annuel / 12.
- ⚠️ **Approximation pilot** : le moteur reçoit déjà « brut − CNSS
  salariale » ; l'abattement 20 % est appliqué sur cette base (≈ 19,4 % du
  brut au lieu de 20 % exactement). Différence négligeable, documentée.

## 2. Contribution Nationale (CN)

- 1,5 % sur la part du **brut mensuel > 50 000 XOF** (seuil annuel
  600 000 XOF).
- CN mensuelle = `max(0, base − 50 000) × 0,015`.
- ⚠️ Approximation pilot : appliquée sur « brut − CNSS » (base transmise au
  moteur), pas sur le brut exact.
- **Impôt total mensuel = ITSAS + CN.**

## 3. CNSS Côte d'Ivoire (plafond 1 647 315 XOF/mois)

| Cotisation | Code | Type | Taux | Plafond |
|---|---|---|---|---|
| Retraite salariale | `CNSS_CI_RET_EMP` | employee | 3,2 % | 1 647 315 |
| Retraite patronale | `CNSS_CI_RET_PAT` | employer | 4,5 % | 1 647 315 |
| Prestations familiales | `CNSS_CI_FAM_PAT` | employer | 5,75 % | 1 647 315 |
| Accidents du travail | `CNSS_CI_AT_PAT` | employer | 2,0 % | **non plafonné** |

## 4. Références

- SMIG CI : 75 000 XOF/mois.
- Congés : 2,2 j/mois de travail effectif (Code du travail art. 25.1) ;
  majoration ancienneté +0,2 j/5 ans.
- Heures supplémentaires (art. 21) : 40–48 h +15 %, 48–54 h +35 %,
  > 54 h (nuit/dimanche) +50 %.
- 13ème mois : obligatoire par conventions de branche (OHADA-CI) →
  `thirteenthMonthMandatory() = true`.
- Jours fériés fixes : 1er jan, Lundi de Pâques, 1er mai, Ascension, Lundi
  de Pentecôte, 7 août, 15 août, 1er nov, 15 nov, 25 déc + fêtes
  islamiques (Aïd el-Fitr, Aïd el-Adha, Maouloud — via `islamic_calendar`).

## 5. Implémentation

- `CedeaoPayrollRules` (instance CI) : `defaultTaxSlabs()` (5 tranches
  annuelles ITSAS), `calculateIncomeTax()` (ITSAS + CN), `socialContributions()`
  (4 codes CNSS), `calculateSocialCharges()` (décomposition légale avec
  plafonds via `computeContribution`), `professionalExpensesDeduction()`
  (20 %, sans cap), `overtimeRateTiers()` (3 paliers), `thirteenthMonthMandatory()`
  (true), `confidenceLevel()` → `pilot`.
- Les autres membres UEMOA (ML/BF/BJ/TG/NE) restent sur le placeholder
  (issue #1829 pour BF/ML).

## 6. Golden tests

`GoldenCiItsasTest` (tests/Feature/Payroll/Golden) : ITSAS+CN sur 5 bruts
(60k → 150 ; 100k → 1 350 ; 500k → 58 083,33 ; 1M → 163 000 ; 3M → 655 500),
CNSS plafonnée (3M → 52 714,08 / 228 849,79 — AT non plafonné),
abattement 20 %, HS 3 paliers, 13ème mois, confidence pilot.
`AbstractCountryRulesCapTest::test_ivory_coast_cnss_capped_at_1647315_xof`
mis à jour sur les taux légaux.
