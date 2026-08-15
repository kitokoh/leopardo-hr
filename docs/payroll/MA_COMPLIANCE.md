# 🇲🇦 Référentiel de conformité paie — Maroc (MA)

> **Programme multi-pays (issues #2119/#2260)** — Référentiel légal versionné du moteur de paie marocain.
> ⚠️ **Statut : PILOT** — valeurs implémentées depuis sources publiques (CGI Maroc, CNSS), **à valider par expert-comptable marocain avant passage en production** (registre `VALIDATION_EXPERTE.md`).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| CNSS salariale 4,48 % (plaf. 6 000 MAD/mois) | ✅ implémentée (pilot) | CNSS | à valider |
| CNSS patronale 8,98 % (plaf. 6 000 MAD/mois) | ✅ implémentée (pilot) | CNSS | à valider |
| AMO salariale 2,26 % (non plafonnée) | ✅ implémentée (pilot) | loi 65-00 | à valider |
| AMO patronale 4,11 % (non plafonnée) | ✅ implémentée (pilot) | loi 65-00 | à valider |
| **Abattement frais professionnels 35 % (min 2 500 / max 30 000 MAD/an)** | ✅ implémentée (pilot, #2260) | CGI art. 58 | à valider |
| IR (barème annuel 6 tranches 0→38 %) | ✅ implémentée (pilot) | CGI art. 59 | à valider |
| SMIG (3 111 MAD/mois) | ✅ implémentée (pilot) | décret | à valider |
| Jours fériés | 📝 placeholder (PA2-COUNTRY-012) | — | — |

## 1. IR — abattement frais professionnels (CGI Maroc art. 58)

L'impôt sur le revenu (IR) marocain est calculé sur le **salaire brut annuel diminué**
des cotisations sociales (CNSS + AMO) **puis de l'abattement frais professionnels** :

- taux : **35 %** du salaire brut ;
- plancher : **2 500 MAD/an** (≈ 208,33 MAD/mois) ;
- plafond : **30 000 MAD/an** (2 500 MAD/mois).

```
assiette IR mensuelle = brut − CNSS salariale − AMO salariale − abattement
abattement = clamp(brut × 35 %, 208,33 ; 2 500)  # valeurs mensuelles
```

Le barème annuel (CGI art. 59) s'applique ensuite sur 12 mois :

| Tranche annuelle (MAD) | Taux | Déduction fixe |
|---|---|---|
| 0 – 30 000 | 0 % | 0 |
| 30 001 – 50 000 | 10 % | 3 000 |
| 50 001 – 60 000 | 20 % | 8 000 |
| 60 001 – 80 000 | 30 % | 14 000 |
| 80 001 – 180 000 | 34 % | 17 200 |
| > 180 000 | 38 % | 24 400 |

**Historique** : l'abattement n'était pas appliqué avant #2260 (IR calculé
directement sur `(brut − cotisations) × 12`) → sur-imposition de tous les
salariés (ex. SMIG 3 111 : IR 40,13 au lieu de 0 ; cadre 10 000 : 1 798,43 au
lieu de 948,43). Implémenté dans `MoroccoPayrollRules::calculateIncomeTax()`
via la méthode dédiée `professionalExpensesDeduction()` (constitution §III —
jamais de calcul inline).

## 2. Cotisations sociales (CNSS / AMO)

| Cotisation | Salarié | Patron | Plafond |
|---|---|---|---|
| CNSS | 4,48 % | 8,98 % | 6 000 MAD/mois |
| AMO | 2,26 % | 4,11 % | aucun |

## 3. Golden tests

`GoldenMaPayrollTest` — 5 cas calculés à la main (§1, #2260) :

| Brut mensuel | Salarié (CNSS+AMO) | Abattement | IR mensuel | Net |
|---|---|---|---|---|
| 3 111 (SMIG) | 209,68 | 1 088,85 | 0,00 | 2 901,32 |
| 10 000 | 494,80 | 2 500 (plaf.) | 948,43 | 8 556,77 |
| 60 000 | 1 624,80 | 2 500 (plaf.) | 19 199,24 | 39 175,96 |
| 200 000 | 4 788,80 | 2 500 (plaf.) | 71 196,92 | 124 014,28 |
| 500 | 33,70 | 208,33 (min) | 0,00 | 466,30 |

## 4. Écarts ouverts

- Validation experte des taux CNSS/AMO/IR et des bornes de l'abattement
  (registre `VALIDATION_EXPERTE.md` — MA).
- Jours fériés légaux : wiring source officielle (PA2-COUNTRY-012).
