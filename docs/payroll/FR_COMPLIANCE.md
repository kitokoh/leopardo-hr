# France — Fiche de conformité paie (FR)

> Statut : 🟡 `pilot` (cible `production`) — à valider par expert-comptable
> local avant passage `production` (constitution §VIII, registre
> `VALIDATION_EXPERTE.md`). Issue : #2119 (golden tests), #1904.

## 1. Impôt sur le revenu — CGI FR art. 197 (barème 2025)

Barème ANNUEL (€) appliqué par le moteur (revenu imposable =
`(brut − cotisations) × 12`) :

| Tranche annuelle (€) | Taux |
|---|---|
| 0 – 11 294 | 0 % |
| 11 295 – 28 797 | 11 % |
| 28 798 – 82 341 | 30 % |
| 82 342 – 177 106 | 41 % |
| > 177 106 | 45 % |

⚠️ **Écarts pilot** (modèle simplifié) : pas de quotient familial, de
décote, de prélèvement à la source mensualisé ni de plafonds Sécurité
sociale (PASS) — le modèle moteur est une approximation pilote pour
simulation, pas un bulletin de paie certifié. À compléter avant toute
promotion `production`.

## 2. Cotisations sociales — Code de la Sécurité sociale, CSG/CRDS

| Cotisation | Salarial | Patronal |
|---|---|---|
| Sécurité sociale (modèle) | 7,5 % | 30,0 % |
| CSG (assiette 98,25 %) | 9,2 % | — |
| CRDS (assiette 98,25 %) | 0,5 % | — |

## 3. Valeurs de référence

- SMIC : 1 766 €/mois (brut).
- Golden tests : `GoldenFrPayrollTest` (3 cas calculés à la main : SMIC
  1 766 → net 1 407,60 · cadre 3 500 → net 2 592,24 · haut salaire 8 000 →
  net 5 205,79).

## 4. Sources

- CGI FR art. 197 (barème 2025) ; Code de la Sécurité sociale ; CSG/CRDS.
