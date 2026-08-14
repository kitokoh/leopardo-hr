# 🇹🇳 Référentiel de conformité paie — Tunisie (TN)

> **Issue #2119** — Fiche pays courte (allowlist historique #1875/#1904).
> ⚠️ À valider par un expert-comptable local avant « production ». Sources :
> CGI Tunisie (IRPP salaires), CNSS, Code du travail (art. 79/90).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (5 tranches annuelles 0–35 %) | ✅ pilot | CGI TN (barème indicatif) | à valider expert |
| CNSS salariale 9,18 % / patronale 16,57 % | ✅ pilot | CNSS | à valider expert |
| SMIG 480 TND/mois | ✅ | — | à valider |
| HS (+25 %) | ✅ pilot | Code du travail art. 90 | à valider expert |

## 1. Barème IR (ANNUEL progressif)

| Tranche annuelle (TND) | Taux |
|---|---|
| 0 – 5 000 | 0 % |
| 5 001 – 20 000 | 26 % |
| 20 001 – 30 000 | 28 % |
| 30 001 – 50 000 | 32 % |
| > 50 000 | 35 % |

Assiette = brut − CNSS salariale.

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS | 9,18 % / 16,57 % | salarié/employeur | non plafonné |

## 3. Golden tests

`GoldenTnPayrollTest` — SMIG 480 (net 430,93 · IR 5,01) · cadre 1 500
(IR 245,86) · haut salaire 4 000 (IR 920,83).

## 4. Sources

- CGI Tunisie (barème IR salaires indicatif), CNSS, Code du travail art. 79/90
- Vérifié le : 2026-08-14 (à confirmer par expert-comptable)
