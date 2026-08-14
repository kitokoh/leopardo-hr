# 🇲🇦 Référentiel de conformité paie — Maroc (MA)

> **Issue #2119** — Fiche pays courte (allowlist historique #1875/#1904 : pays
> pilot pré-datant le playbook). ⚠️ À valider par un expert-comptable local
> avant passage à « production ». Sources : CGI Maroc (IR), CNSS, AMO, Code
> du travail (loi 65-99).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (6 tranches annuelles, taux 0–38 % + déduction forfaitaire) | ✅ pilot | CGI Maroc (barème indicatif) | à valider expert |
| CNSS salariale 4,48 % / patronale 8,98 % (plaf. 6 000 MAD) | ✅ pilot | CNSS | à valider expert |
| AMO salariale 2,26 % / patronale 4,11 % (non plafonné) | ✅ pilot | AMO | à valider expert |
| SMIG 3 111 MAD/mois | ✅ | — | à valider |
| HS (+25 % jour) | ✅ pilot | loi 65-99 art. 201 | à valider expert |

## 1. Barème IR (ANNUEL, taux + déduction forfaitaire)

| Tranche annuelle (MAD) | Taux | Déduction |
|---|---|---|
| 0 – 30 000 | 0 % | 0 |
| 30 001 – 50 000 | 10 % | 3 000 |
| 50 001 – 60 000 | 20 % | 8 000 |
| 60 001 – 80 000 | 30 % | 14 000 |
| 80 001 – 180 000 | 34 % | 17 200 |
| > 180 000 | 38 % | 24 400 |

`impôt = max(0, revenu annuel × taux − déduction)` / 12. Assiette =
brut − CNSS − AMO.

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS | 4,48 % / 8,98 % | salarié/employeur | 6 000 MAD/mois |
| AMO | 2,26 % / 4,11 % | salarié/employeur | non plafonné |

## 3. Golden tests

`GoldenMaPayrollTest` — SMIG 3 111 (net 2 861,19 · IR 40,13) · cadre 8 000
(IR 1 133,80) · haut salaire 20 000 (IR 5 292,76).

## 4. Sources

- CGI Maroc (barème IR indicatif), CNSS/AMO, loi 65-99 (HS 44 h/sem, +25 %)
- Vérifié le : 2026-08-14 (à confirmer par expert-comptable)
