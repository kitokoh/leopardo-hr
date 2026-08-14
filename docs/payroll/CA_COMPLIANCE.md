# 🇨🇦 Référentiel de conformité paie — Canada (CA)

> **Issue #2119** — Fiche pays courte (allowlist historique #1875/#1904).
> Barème FÉDÉRAL indicatif uniquement ; l'impôt provincial n'est pas
> modélisé (`confidenceLevel() = 'placeholder'`). Sources : CRA (CPP/EI),
> barème fédéral IR indicatif, Canada Labour Code.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR fédéral (5 tranches annuelles 15–33 %) | ⏳ placeholder | barème indicatif | à valider |
| CPP salariale/patronale 5,95 % | ✅ pilot | CRA | à valider expert |
| EI salariale 1,66 % / patronale 2,32 % | ✅ pilot | CRA | à valider expert |
| Salaire min. réf. 2 999 CAD/mois | ⏳ placeholder | fédéral approx. | à valider |
| HS (+50 %, seuil provincial 40–48 h) | ✅ pilot | Labour Code | à valider expert |

## 1. Barème IR fédéral (ANNUEL progressif, indicatif)

| Tranche annuelle (CAD) | Taux |
|---|---|
| 0 – 55 867 | 15 % |
| 55 868 – 111 733 | 20,5 % |
| 111 734 – 173 205 | 26 % |
| 173 206 – 246 752 | 29 % |
| > 246 753 | 33 % |

Assiette = brut − CPP − EI (salariales). Impôt provincial NON modélisé.

## 2. Cotisations

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CPP/RPC | 5,95 % / 5,95 % | salarié/employeur | non plafonné (modèle) |
| Assurance-emploi | 1,66 % / 2,32 % | salarié/employeur | non plafonné (modèle) |

## 3. Golden tests

`GoldenCaPayrollTest` — salaire min. 2 999 (net 2 355,16 · IR 415,62) ·
cadre 8 000 (IR 1 259,14) · haut salaire 20 000 (IR 4 157,44).

## 4. Sources

- CRA (CPP/EI), barème fédéral IR indicatif, Canada Labour Code (HS)
- Vérifié le : 2026-08-14 (à confirmer par expert-comptable canadien)
