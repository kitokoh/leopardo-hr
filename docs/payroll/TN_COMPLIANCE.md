# 🇹🇳 Référentiel de conformité paie — Tunisie (TN)

> **Issues #2119 / #2261** — Fiche pays courte (allowlist historique
> #1875/#1904). ⚠️ À valider par un expert-comptable local avant
> « production ». Sources : CGI Tunisie (IRPP salaires, art. 39 abattement),
> CNSS, Code du travail (art. 79/90).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (5 tranches annuelles 0–35 %) | ✅ pilot | CGI TN (barème indicatif) | à valider expert |
| Abattement IRPP 10 % (min 1 000 / max 1 500 TND/an) | ✅ implémentée | CGI TN art. 39 | à valider expert |
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

**Abattement (CGI TN art. 39, issue #2261)** : 10 % du revenu annuel
imposable, borné **1 000 / 1 500 TND/an** — appliqué AVANT le barème via la
méthode dédiée `TunisiaPayrollRules::applyAnnualAbatement()` (constitution
§III : la valeur légale vit dans une méthode, pas inline).

```
assiette IR mensuelle = brut − CNSS salariale (9,18 %)
revenu annuel imposable = assiette × 12 − abattement art. 39 (10 %, bornes)
```

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS | 9,18 % / 16,57 % | salarié/employeur | non plafonné |

## 3. Golden tests

`GoldenTnPayrollTest` — SMIG 480 (IR 0, net 435,94) · cadre 1 000
(abattement 1 089,84 → IR 104,19) · haut salaire 3 500 (abattement plafonné
1 500 → IR 735,52) + bornes de la méthode dédiée.

## 4. Sources

- CGI Tunisie (barème IR salaires indicatif + art. 39 abattement 10 %),
  CNSS, Code du travail art. 79/90
- Vérifié le : 2026-08-14 (à confirmer par expert-comptable)
