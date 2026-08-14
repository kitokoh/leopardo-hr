# Canada — Fiche de conformité paie (CA, fédéral)

> Statut : 🔴/🟡 `placeholder` dans le code (modèle FÉDÉRAL simplifié) —
> à valider par expert-comptable local avant toute promotion
> (constitution §VIII, registre `VALIDATION_EXPERTE.md`). Issues : #2119
> (golden tests), #1904 (validation experte).

## 1. Impôt fédéral sur le revenu — Loi de l'impôt sur le revenu (barème 2024)

Barème ANNUEL FÉDÉRAL (CAD) appliqué par le moteur :

| Tranche annuelle (CAD) | Taux |
|---|---|
| 0 – 55 867 | 15 % |
| 55 868 – 111 733 | 20,5 % |
| 111 734 – 173 205 | 26 % |
| 173 206 – 246 752 | 29 % |
| > 246 752 | 33 % |

⚠️ **Écarts** (modèle fédéral simplifié) : **pas d'impôt provincial**, de
crédits d'impôt, d'exemption personnelle de base ni de plafonds RPC/AE
annuels. Les salaires réels canadiens cumulent fédéral + provincial — le
modèle moteur est une approximation pour simulation uniquement.

## 2. Cotisations sociales fédérales — RPC (Loi C-23), AE (Loi C-45)

| Cotisation | Salarial | Patronal |
|---|---|---|
| RPC (régime de pensions) | 5,95 % | 5,95 % |
| Assurance-emploi | 1,66 % | 2,32 % |

## 3. Valeurs de référence

- Salaire minimum fédéral approximatif : 2 999 CAD/mois (référence
  placeholder ; les minimums provinciaux diffèrent).
- Golden tests : `GoldenCaPayrollTest` (3 cas calculés à la main : min.
  2 999 → net 2 355,16 · cadre 5 000 → net 3 926,57 · haut salaire
  10 000 → net 7 601,06).

## 4. Sources

- Loi de l'impôt sur le revenu (barème fédéral 2024) ; RPC ; AE.
