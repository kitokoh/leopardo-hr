# Maroc — Fiche de conformité paie (MA)

> Statut : 🟡 `pilot` — à valider par expert-comptable local avant passage
> `production` (constitution §VIII, registre `VALIDATION_EXPERTE.md`).
> Issue de suivi : #2119 (golden tests), #1904 (validation experte).

## 1. Impôt sur le revenu (IR) — CGI MA art. 57-64

Barème ANNUEL (revenu net imposable, MAD) appliqué par le moteur :

| Tranche annuelle (MAD) | Taux | Déduction forfaitaire |
|---|---|---|
| 0 – 30 000 | 0 % | 0 |
| 30 001 – 50 000 | 10 % | 3 000 |
| 50 001 – 60 000 | 20 % | 8 000 |
| 60 001 – 80 000 | 30 % | 14 000 |
| 80 001 – 180 000 | 34 % | 17 200 |
| > 180 000 | 38 % | 24 400 |

⚠️ **Écart pilot** : l'abattement frais professionnels de 35 % (CGI MA,
min 2 500 / max 30 000 MAD/an) n'est **pas appliqué** par le moteur — IR
calculé directement sur `(brut − cotisations) × 12`. À implémenter avant
promotion `production` (à confirmer par expert, #1904).

## 2. Cotisations sociales — décret 2-77-649 (CNSS), loi 65-00 (AMO)

| Cotisation | Salarial | Patronal | Plafond |
|---|---|---|---|
| CNSS | 4,48 % | 8,98 % | 6 000 MAD/mois |
| AMO | 2,26 % | 4,11 % | aucun |

## 3. Valeurs de référence

- SMIG : 3 111 MAD/mois.
- Golden tests : `GoldenMaPayrollTest` (3 cas calculés à la main : SMIG
  3 111 → net 2 861,19 · cadre 5 000 → net 4 397,07 · haut salaire 12 000 →
  net 8 996,93).

## 4. Sources

- CGI Maroc (Loi 43-06, art. 57-64) — barème IR.
- Décret n° 2-77-649 (CNSS) ; loi 65-00 (AMO) ; SMIG bulletin officiel.
