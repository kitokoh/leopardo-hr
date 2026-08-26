# 🇧🇫 Référentiel de conformité paie — Burkina Faso (BF)

> **Issue #1829** — Référentiel légal versionné du moteur de paie burkinabè
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` (instance BF) de « placeholder »
> à « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux CGI 2024, CNSS, Code du travail).
> Sources : DGI Burkina, CNSS, Code du travail.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IUTS (6 tranches annuelles) | ✅ implémentée (pilot) | CGI 2024 | à valider expert |
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
| 4 500 001 – 6 000 000 | 23,6 % |
| > 6 000 000 | 27,5 % |

⚠️ Les taux IUTS burkinabè sont « tout compris » (contribution communale
incluse). À valider sur le site de la DGI Burkina. Issue #1915 : la tranche
`> 6 000 000 @ 27,5 %` (CGI BF) manquait — l'ancien barème fusionnait les
deux dernières tranches à 23,6 % (sous-imposition au-delà de ~500 000
FCFA/mois) ; corrigé et verrouillé par `GoldenBfPayrollTest` (cas 1 150 500
→ 258 212,50 et frontière 6 M annuel).

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

| Catégorie | Préavis (jours OUVRÉS, #2219) |
|---|---|
| Ouvriers | 6 j (8 j calendaires) |
| Employés / Techniciens | 22 j (1 mois) |
| Cadres | 66 j (3 mois) |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — catégorie du contrat = suivi.

## 8. Jours fériés

Fixes : 1ᵉʳ janvier, 3 janvier (Fête de la Révolution), 8 mars, 1ᵉʳ mai,
11 décembre (Fête Nationale), 25 décembre + fêtes islamiques mobiles (via
table `islamic_calendar`, #1812). Gestion dynamique via #1811.

## 11. Déclarations mensuelles — périmètre du CSV CNSS (décision #2158)

Générateur : `CedeaoCnsDeclarationGenerator` — `GET /api/v1/payroll-runs/{run}/declarations/cnss-bf` (managers `principal`/`comptable`).

| Rubrique | Salarial | Patronal | Plafond (XOF/mois) |
|---|---|---|---|
| Retraite | 5,5 % | 6,5 % | 900 000 |
| Prestations familiales | — | 7,0 % | 900 000 |
| Risques professionnels (AT) | — | 3,5 % | non plafonné |

Une ligne par bulletin validé (matricule `employees.cnss_bf_matricule`) + ligne TOTAUX.
Canal déclaratif : CNSS Burkina Faso (www.cnss.bf) — montants `pilot` à valider par
expert-comptable local (registre `docs/payroll/VALIDATION_EXPERTE.md`, #1904).

> **Statut code (2026-08-26, issue #5623)** : `confidenceLevel()` de
> `CedeaoPayrollRules` retourne **pilot** pour ce pays (barèmes implémentés,
> non validés par un expert-comptable local). L'UI paie affiche désormais un
> badge/avertissement pour tout pays au niveau **placeholder** (ex. membres
> CEDEAO hors CI/BF/ML/TG, membres CEMAC hors CM/GA/CG) et exige une
> confirmation explicite avant validation d'un run placeholder.
