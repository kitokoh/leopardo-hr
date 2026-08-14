# 🇹🇷 Référentiel de conformité paie — Turquie (TR)

> **Issue #2119** — Fiche pays courte (allowlist historique #1875/#1904).
> ⚠️ **N'est PAS un substitut à un logiciel certifié ni à un mali müşavir**
> (voir `complianceWarning()`). Sources : Labor Law No. 4857, barème IR
> indicatif, taux SGK généraux.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (5 tranches annuelles 15–40 %) | ✅ pilot | barème indicatif | à valider expert |
| SGK salariale 14 % / patronale 20,5 % | ✅ pilot | SGK | à valider expert |
| Chômage salariale 1 % / patronale 2 % | ✅ pilot | İşsizlik | à valider expert |
| Salaire min. 20 002 TRY/mois | ✅ | — | à valider |
| HS (+50 %) | ✅ pilot | Law 4857 art. 41 | à valider expert |

## 1. Barème IR (ANNUEL progressif, indicatif)

| Tranche annuelle (TRY) | Taux |
|---|---|
| 0 – 110 000 | 15 % |
| 110 001 – 230 000 | 20 % |
| 230 001 – 580 000 | 27 % |
| 580 001 – 3 000 000 | 35 % |
| > 3 000 000 | 40 % |

Assiette = brut − SGK − chômage (salariales).

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| SGK | 14 % / 20,5 % | salarié/employeur | non plafonné |
| Chômage | 1 % / 2 % | salarié/employeur | non plafonné |

## 3. Golden tests

`GoldenTrPayrollTest` — salaire min. 20 002 (net 14 059,69 · IR 2 942,01) ·
cadre 40 000 (IR 7 380,00) · haut salaire 100 000 (IR 24 083,33).

## 4. Sources

- Labor Law No. 4857 (45 h/sem, HS art. 41 +50 %), barème IR indicatif,
  taux SGK/chômage généraux
- Vérifié le : 2026-08-14 (à confirmer par mali müşavir / expert)
