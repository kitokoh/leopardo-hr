# 🇲🇱 Référentiel de conformité paie — Mali (ML)

> **Issue #1829** — Référentiel légal versionné du moteur de paie malien
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` (instance ML) de « placeholder »
> à « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux CGI 2024, INPS, Code du travail).
> Sources : DGI Mali, INPS, Code du travail.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| ITS (6 tranches annuelles) | ✅ implémentée (pilot) | CGI 2024 | à valider expert |
| INPS retraite 3,6 % / 7,4 % (plaf. 3 000 000) | ✅ implémentée (pilot) | INPS | à valider expert |
| INPS famille patronale 4,0 % | ✅ implémentée (pilot) | INPS | à valider expert |
| INPS AT patronale 2,0 % (non plafonné) | ✅ implémentée (pilot) | INPS — 1-4 % selon secteur | à valider expert |
| SMIG 40 000 XOF/mois | ✅ implémentée | — | à valider |
| Congés (2,5 j/mois) | 📝 à documenter/test | Code du travail | — |
| HS (+15 % / +35 %) | ✅ implémentée (pilot) | Code du travail OHADA | à valider expert |
| Préavis (8 j / 1 m / 3 m) | ✅ implémentée (pilot, niveau employé) | Code du travail | à valider expert |
| Jours fériés fixes ML | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. ITS — Impôt sur les Traitements et Salaires

**Barème ANNUEL** (`CedeaoPayrollRules::defaultTaxSlabs()` pour ML —
CGI Mali 2024) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 540 000 | 0 % |
| 540 001 – 1 320 000 | 5 % |
| 1 320 001 – 2 040 000 | 10 % |
| 2 040 001 – 3 480 000 | 15 % |
| 3 480 001 – 6 360 000 | 20 % |
| > 6 360 000 | 30 % |

À valider sur le site de la DGI Mali.

## 2. Assiette ITS

```
assiette ITS mensuelle = brut − INPS salariale
```

## 3. INPS — Institut National de Prévoyance Sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| INPS retraite salariale | 3,6 % | salarié | 3 000 000 XOF/mois |
| INPS retraite patronale | 7,4 % | employeur | 3 000 000 XOF/mois |
| INPS prestations familiales patronale | 4,0 % | employeur | aucun |
| INPS risques professionnels patronale | 2,0 % | employeur | aucun (pilote, 1-4 % selon secteur) |

Codes : `INPS_ML_RET_EMP`, `INPS_ML_RET_PAT`, `INPS_ML_FAM_PAT`,
`INPS_ML_AT_PAT`.

## 4. SMIG

**40 000 XOF/mois** — `CedeaoPayrollRules::minimumWage()` pour ML.

## 5. Congés payés

2,5 j/mois — 📝 à documenter/test.

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

Fixes : 1ᵉʳ janvier, 20 janvier (Fête de l'Armée), 26 mars, 1ᵉʳ mai,
25 mai (Fête Nationale), 22 septembre (Fête de l'Indépendance),
25 décembre + fêtes islamiques mobiles (via table `islamic_calendar`,
#1812). Gestion dynamique via #1811.
