# Conformité paie — Sénégal (SN_COMPLIANCE.md)

> Issue #1827 — complétude pilot → production : CFCE, TRIMF, IPRES cadres
> T2, plafonds, abattement frais pro.
>
> **Statut : PILOT** — à valider par un expert-comptable sénégalais avant
> passage à « production » (confidenceLevel() reste `pilot`).

## 1. IR salarial (CGI Sénégal)

Barème mensuel progressif (assiette = brut − IPRES − abattement 30 %) :
0 % jusqu'à 630 000, puis 20/30/35/37/40 % (tranches `defaultTaxSlabs`).
**Abattement frais professionnels : 30 % du brut, non plafonné.**

## 2. TRIMF — Taxe Représentative des Impôts du Minimum Fiscal

Taxe forfaitaire mensuelle retenue sur le salarié (`calculateBracketTax`) :

| Tranche brut mensuel (XOF) | TRIMF |
|---|---|
| 0 – 25 000 | 900 |
| 25 001 – 75 000 | 2 700 |
| 75 001 – 150 000 | 5 400 |
| 150 001 – 350 000 | 9 000 |
| 350 001 – 700 000 | 18 000 |
| > 700 000 | 36 000 |

## 3. Cotisations sociales

| Cotisation | Code | Type | Taux | Plafond |
|---|---|---|---|---|
| IPRES régime général T1 | `IPRES_SN_EMP` / `IPRES_SN_PAT` | salarié/patronal | 5,6 % / 8,4 % | 432 000 |
| IPRES régime cadres T2 | `IPRES_SN_EMP_T2` / `IPRES_SN_PAT_T2` | salarié/patronal | 2,4 % / 3,6 % | tranche 432 001 – 2 160 000 |
| CSS famille | `CSS_SN_PAT` | patronal | 3,0 % | aucun |
| CSS AT | `CSS_SN_PAT_AT` | patronal | 1,0 % (pilote) | aucun |
| CFCE | `CFCE_SN_PAT` | patronal | 3,0 % | aucun |

⚠️ **Approximation pilot** : le régime cadres T2 est appliqué à tous les
salariés (le moteur ne distingue pas encore la catégorie employé) —
surestimation pour les non-cadres. À affiner avec un flag « cadre » sur
l'employé.

## 4. Références

- SMIG : 58 900 XOF/mois.
- Congés : 2,1 j/mois = 25,2 j/an ; majoration +1 j/5 ans d'ancienneté.
- Préavis (art. L.45) : 8 j ouvriers, 1 mois employés/techniciens, 3 mois
  cadres — approximation pilot par ancienneté (< 1 an → 8 j ; < 5 ans →
  1 mois ; ≥ 5 ans → 3 mois) dans `noticePeriodDays()`.
- Jours fériés fixes : 1er jan, 4 avr, 1er mai, 15 août, 1er nov, 25 déc +
  Korité, Tabaski, Gamou, Tamkharit (islamiques via `islamic_calendar`).

## 5. Implémentation

`SenegalPayrollRules` : `calculateBracketTax()` (TRIMF 6 tranches),
`socialContributions()` (7 codes), `calculateSocialCharges()` (T1 plafonné +
T2 tranche + CSS famille/AT non plafonnés + CFCE), `professionalExpensesDeduction()`
(30 %), `noticePeriodDays()` (matrice ancienneté), `confidenceLevel() = pilot`.

## 6. Golden tests

`GoldenSnPilotTest` (TRIMF 6 bruts, abattement, préavis, liste cotisations,
confidence) + `AbstractCountryRulesCapTest` SN aligné (1M → 37 824 / 126 736 ;
200k → 11 200 / 30 800).
