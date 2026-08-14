# 🇧🇫 Référentiel de conformité paie — Burkina Faso (BF)

> **Issue #1829** — Référentiel légal versionné du moteur de paie burkinabè
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` (instance BF) de « placeholder »
> à « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux CGI 2024, CNSS, Code du travail).
> Sources : DGI Burkina, CNSS, Code du travail.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IUTS (5 tranches annuelles) | ✅ implémentée (pilot) | CGI 2024 | à valider expert |
| CNSS retraite 5,5 % / 6,5 % (plaf. 900 000) | ✅ implémentée (pilot) | CNSS | à valider expert |
| CNSS famille patronale 7,0 % (même plaf.) | ✅ implémentée (pilot) | CNSS | à valider expert |
| CNSS AT patronale 3,5 % (non plafonné) | ✅ implémentée (pilot) | CNSS — variable selon risque | à valider expert |
| SMIG 34 664 XOF/mois | ✅ implémentée | — | à valider |
| Congés (2,5 j/mois) | 📝 à documenter/test | Code travail BF art. 152 | — |
| HS (+15 % / +35 %) | ✅ implémentée (pilot) | Code du travail OHADA | à valider expert |
| Préavis (8 j / 1 m / 3 m) | ✅ implémentée (pilot, niveau employé) | Code du travail | à valider expert |
| Jours fériés fixes BF | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. IUTS — Impôt Unique sur les Traitements et Salaires

**Barème ANNUEL** (`CedeaoPayrollRules::defaultTaxSlabs()` pour BF —
CGI Burkina 2024) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 600 000 | 0 % |
| 600 001 – 1 500 000 | 12,1 % |
| 1 500 001 – 3 000 000 | 13,9 % |
| 3 000 001 – 4 500 000 | 18,7 % |
| > 4 500 000 | 23,6 % |

⚠️ Les taux IUTS burkinabè sont « tout compris » (contribution communale
incluse). À valider sur le site de la DGI Burkina.

## 2. Assiette IUTS

```
assiette IUTS mensuelle = brut − CNSS salariale
```

## 3. CNSS — Cotisations sécurité sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS retraite salariale | 5,5 % | salarié | 900 000 XOF/mois |
| CNSS retraite patronale | 6,5 % | employeur | 900 000 XOF/mois |
| CNSS prestations familiales patronale | 7,0 % | employeur | 900 000 XOF/mois |
| CNSS risques professionnels patronale | 3,5 % | employeur | **non plafonné** (pilote) |

Codes : `CNSS_BF_RET_EMP`, `CNSS_BF_RET_PAT`, `CNSS_BF_FAM_PAT`,
`CNSS_BF_AT_PAT`.

## 4. SMIG

**34 664 XOF/mois** — `CedeaoPayrollRules::minimumWage()` pour BF.

## 5. Congés payés

2,5 j/mois (Code du travail BF art. 152) — 📝 à documenter/test.

## 6. Heures supplémentaires

+15 % pour les 8 premières heures/semaine, +35 % au-delà (Code du travail
OHADA) — paliers `1.15` / `1.35`, seuil légal 40 h/semaine.

## 7. Préavis

| Catégorie | Préavis |
|---|---|
| Ouvriers | 8 jours |
| Employés / Techniciens | 1 mois |
| Cadres | 3 mois |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — catégorie du contrat = suivi.

## 8. Jours fériés

Fixes : 1ᵉʳ janvier, 3 janvier (Fête de la Révolution), 8 mars, 1ᵉʳ mai,
11 décembre (Fête Nationale), 25 décembre + fêtes islamiques mobiles (via
table `islamic_calendar`, #1812). Gestion dynamique via #1811.
