# 🇫🇷 Référentiel de conformité paie — France (FR)

> Fiche courte issue #2119 (golden tests). ⚠️ À valider par un expert-comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot` — modèle SIMPLIFIÉ (prélèvement à la source non modélisé).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR | ✅ implémentée (pilot) | CGI art. 197 (barème 2024) | à confirmer |
| Cotisations sociales | ✅ implémentée (pilot, simplifié) | SS + CSG + CRDS | à confirmer |
| SMIC | ✅ 1 766 €/mois | SMIC 2024 | à confirmer |

## 1. Barème IR (mensuel = annuel / 12)

Tranches ANNUELES (assiette = brut − cotisations salariales) : 0–11 294 € 0 % · 11 295–28 797 € 11 % · 28 798–82 341 € 30 % · 82 342–177 106 € 41 % · > 177 107 € 45 %.

## 2. Cotisations sociales (simplifié)

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Sécurité sociale | 7,5 % / 30,0 % | salarié / employeur | non plafonné (modèle) |
| CSG | 9,2 % | salarié | base 98,25 % du brut |
| CRDS | 0,5 % | salarié | base 98,25 % du brut |

## 3. SMIC

1 766 €/mois (SMIC 2024).
