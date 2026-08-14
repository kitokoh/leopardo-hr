# 🇫🇷 Référentiel de conformité paie — France (FR)

> **Issue #2119** — Fiche pays courte (allowlist historique #1875/#1904).
> ⚠️ **N'est PAS un substitut à un logiciel de paie certifié (DSN) ni à un
> expert-comptable** (voir `complianceWarning()`). Sources : Code du travail,
> barème IR indicatif, taux sociaux généraux.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (5 tranches annuelles 0–45 %) | ✅ pilot | barème indicatif 2024 | à valider expert |
| SS salariale 7,5 % / patronale 30 % | ✅ pilot | taux généraux | à valider expert |
| CSG 9,2 % + CRDS 0,5 % (assiette 98,25 %) | ✅ pilot | taux légaux | à valider expert |
| SMIC 1 766 EUR/mois | ✅ | — | à valider |
| HS (+25 % 8 h, +50 % ensuite) | ✅ pilot | C. trav. L3121-36 | à valider expert |

## 1. Barème IR (ANNUEL progressif, indicatif)

| Tranche annuelle (EUR) | Taux |
|---|---|
| 0 – 11 294 | 0 % |
| 11 295 – 28 797 | 11 % |
| 28 798 – 82 341 | 30 % |
| 82 342 – 177 106 | 41 % |
| > 177 107 | 45 % |

Assiette = brut − cotisations salariales (SS + CSG/CRDS).

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Sécurité sociale | 7,5 % / 30 % | salarié/employeur | non plafonné |
| CSG | 9,2 % | salarié | assiette 98,25 % |
| CRDS | 0,5 % | salarié | assiette 98,25 % |

## 3. Golden tests

`GoldenFrPayrollTest` — SMIC 1 766 (net ≈ 1 407,60 · IR 57,65) · cadre 3 000
(IR 187,25) · haut salaire 8 000 (IR 1 431,79).

## 4. Sources

- Code du travail (durée 35 h, HS L3121-27/36), barème IR indicatif 2024,
  taux CSG/CRDS/SS généraux
- Vérifié le : 2026-08-14 (à confirmer par expert-comptable)
