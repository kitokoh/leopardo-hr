# 🇲🇦 Référentiel de conformité paie — Maroc (MA)

> Fiche courte issue #2119 (golden tests). ⚠️ À valider par un expert-comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot`.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR | ✅ implémentée (pilot) | CGI Maroc — IR salarial mensuel | à confirmer |
| Cotisations sociales | ✅ implémentée (pilot) | CNSS + AMO (loi 65-00) | à confirmer |
| SMIG | ✅ 3 111 MAD/mois | SMIG secteur non agricole 2024 | à confirmer |
| Heures supplémentaires | ✅ 25 % (pilot) | loi 65-99 art. 201 | à confirmer |

## 1. Barème IR (mensuel = annuel / 12)

Tranches ANNUELES (assiette = brut − CNSS salariale) : 0–30 000 0 % · 30 001–50 000 10 % · 50 001–60 000 20 % · 60 001–80 000 30 % · 80 001–180 000 34 % · > 180 000 38 %. Le calcul applique la déduction forfaitaire par tranche (`fixed_deduction`).

## 2. Cotisations sociales

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS | 4,48 % / 8,98 % | salarié / employeur | 6 000 MAD/mois |
| AMO | 2,26 % / 4,11 % | salarié / employeur | non plafonné |

## 3. SMIG

3 111 MAD/mois (SMIG 2024, secteur non agricole).
