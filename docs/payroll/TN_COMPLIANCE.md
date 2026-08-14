# Tunisie — Fiche de conformité paie (TN)

> Statut : 🟡 `pilot` — à valider par expert-comptable local avant passage
> `production` (constitution §VIII, registre `VALIDATION_EXPERTE.md`).
> Issues : #2119 (golden tests), #1904 (validation experte).

## 1. Impôt sur le revenu des personnes physiques (IRPP) — loi 89-114 mod.

Barème ANNUEL (TND) appliqué par le moteur :

| Tranche annuelle (TND) | Taux |
|---|---|
| 0 – 5 000 | 0 % |
| 5 001 – 20 000 | 26 % |
| 20 001 – 30 000 | 28 % |
| 30 001 – 50 000 | 32 % |
| > 50 000 | 35 % |

⚠️ **Écart pilot** : l'abattement de 10 % (min 1 000 / max 1 500 TND/an,
CGI TN art. 39) et les déductions pour charges de famille ne sont **pas
appliqués** par le moteur. À implémenter avant promotion `production`
(à confirmer par expert, #1904).

## 2. Cotisations sociales — loi 60-30 mod. (CNSS)

| Cotisation | Salarial | Patronal |
|---|---|---|
| CNSS (régime général) | 9,18 % | 16,57 % |

## 3. Valeurs de référence

- SMIG : 480 TND/mois.
- Golden tests : `GoldenTnPayrollTest` (3 cas calculés à la main : SMIG
  480 → net 430,93 · cadre 1 000 → net 780,40 · haut salaire 3 500 →
  net 2 403,18).

## 4. Sources

- Loi n° 89-114 (IRPP) modifiée par les lois de finances successives.
- Loi n° 60-30 (CNSS) modifiée ; SMIG décret.
