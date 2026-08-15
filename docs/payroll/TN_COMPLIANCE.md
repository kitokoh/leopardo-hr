# 🇹🇳 Référentiel de conformité paie — Tunisie (TN)

> Fiche courte issue #2119 (golden tests). ⚠️ À valider par un expert-comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot`.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IRPP | ✅ implémentée (pilot) | CGI Tunisie — IRPP salarial | à confirmer |
| Cotisations sociales | ✅ implémentée (pilot) | CNSS (loi 96-62) | à confirmer |
| SMIG | ✅ 480 TND/mois | SMIG 2024 | à confirmer |

## 1. Barème IRPP (mensuel = annuel / 12)

Tranches ANNUELES (assiette = brut − CNSS salariale) : 0–5 000 0 % · 5 001–20 000 26 % · 20 001–30 000 28 % · 30 001–50 000 32 % · > 50 000 35 %.

### 1.1 Abattement pour frais professionnels (CGI TN art. 39 — issue #2261)

Avant application du barème progressif, l'assiette annuelle est réduite d'un
abattement de **10 %** du revenu imposable, borné **[1 000 ; 1 500 TND/an]**.

| Salaire brut mensuel | Assiette annuelle | Abattement | Revenu imposable | IR mensuel |
|---|---|---|---|---|
| 480 (SMIG) | 5 231,28 | 1 000 (plancher) | 4 231,28 | 0,00 |
| 2 000 | 21 796,80 | 1 500 (plafond) | 20 296,80 | 331,93 |
| 8 000 | 87 187,20 | 1 500 (plafond) | 85 687,20 | 2 132,54 |

> ⚠️ Niveau `pilot` : ces valeurs restent à confirmer par un expert-comptable
> local avant passage à « production » (issue #1904).

## 2. Cotisations sociales

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS | 9,18 % / 16,57 % | salarié / employeur | non plafonné |

## 3. SMIG

480 TND/mois (SMIG 2024, secteur non agricole).
