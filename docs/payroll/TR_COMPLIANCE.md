# Turquie — Fiche de conformité paie (TR)

> Statut : 🟡 `pilot` — à valider par expert-comptable local avant passage
> `production` (constitution §VIII, registre `VALIDATION_EXPERTE.md`).
> Issues : #2119 (golden tests), #1904 (validation experte).

## 1. Impôt sur le revenu — Gelir Vergisi Kanunu (loi n° 193, barème 2024)

Barème ANNUEL (TRY) appliqué par le moteur :

| Tranche annuelle (TRY) | Taux |
|---|---|
| 0 – 110 000 | 15 % |
| 110 001 – 230 000 | 20 % |
| 230 001 – 580 000 | 27 % |
| 580 001 – 3 000 000 | 35 % |
| > 3 000 000 | 40 % |

⚠️ **Écart pilot** : l'exonération IR du salaire minimum (en vigueur depuis
2022) et les déductions spécifiques ne sont **pas appliquées** par le
moteur. À implémenter avant promotion `production` (à confirmer par expert,
#1904).

## 2. Cotisations sociales — loi SGK n° 5510, loi chômage n° 4447

| Cotisation | Salarial | Patronal |
|---|---|---|
| SGK | 14,0 % | 20,5 % |
| Assurance chômage | 1,0 % | 2,0 % |

## 3. Valeurs de référence

- Salaire minimum : 20 002 TRY/mois (2024).
- Golden tests : `GoldenTrPayrollTest` (3 cas calculés à la main : min.
  20 002 → net 14 059,69 · cadre 45 000 → net 29 722,50 · haut salaire
  120 000 → net 71 966,67).

## 4. Sources

- Gelir Vergisi Kanunu n° 193 (barème 2024) ; SGK n° 5510 ; n° 4447.
