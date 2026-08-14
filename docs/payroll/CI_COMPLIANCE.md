# 🇨🇮 Référentiel de conformité paie — Côte d'Ivoire (CI)

> **Issue #1825** — Référentiel légal versionné du moteur de paie ivoirien
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` de « placeholder » à « pilot ».
> ⚠️ **À valider par un expert-comptable OHADA-CI avant passage à
> « production »** (taux CGI 2024, CNSS, Code du travail CI).
> Sources : CGI Côte d'Ivoire 2024 (art. 116-120), CNSS, Code du travail.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| ITSAS (5 tranches annuelles) | ✅ implémentée (pilot) | CGI 2024 art. 116-120 | à valider expert |
| Contribution Nationale (1,5 % > 50 000) | ✅ implémentée (pilot) | CGI 2024 art. 116-120 | à valider expert |
| Abattement frais pro (20 %, non plafonné) | ✅ implémentée (pilot) | CGI 2024 art. 116 | à valider expert |
| CNSS retraite 3,2 % / 4,5 % (plaf. 1 647 315) | ✅ implémentée (pilot) | CNSS | à valider expert |
| CNSS famille patronale 5,75 % (même plaf.) | ✅ implémentée (pilot) | CNSS | à valider expert |
| CNSS AT patronale 2,0 % (non plafonné) | ✅ implémentée (pilot) | CNSS — variable selon secteur | à valider expert |
| SMIG 75 000 XOF/mois | ✅ implémentée | — | à valider |
| 13ème mois (conventions de branche) | ✅ implémentée (pilot) | pratique généralisée | à valider expert |
| Congés (2,2 j/mois, +0,2 j/5 ans) | 📝 à documenter/test | Code travail CI art. 25.1 | — |
| HS (15 % / 35 % / 50 %) | ✅ implémentée (pilot) | Code travail CI art. 21 | à valider expert |
| Préavis (art. 18) | ✅ implémentée (pilot, niveau employé) | Code travail CI art. 18 | à valider expert |
| Jours fériés fixes CI | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. ITSAS — Impôt sur Traitements, Salaires et Assimilés

**Barème ANNUEL** (implémenté dans `CedeaoPayrollRules::defaultTaxSlabs()`
pour le membre CI — CGI 2024, art. 116-120) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 600 000 | 0 % |
| 600 001 – 2 000 000 | 2 % |
| 2 000 001 – 5 000 000 | 21 % |
| 5 000 001 – 10 000 000 | 24,5 % |
| > 10 000 000 | 29 % |

`calculateIncomeTax()` annualise l'assiette mensuelle (`× 12`), applique le
barème progressif (bornes inclusives), puis ramène le résultat au mois.
⚠️ Pour CI, `calculateIncomeTax()` retourne **l'ITSAS seul** ; la
Contribution Nationale est calculée séparément (§3) et additionnée dans le
bulletin (ligne « Taxe de minimum fiscal »).

## 2. Assiette ITSAS

```
assiette ITSAS mensuelle = brut − CNSS salariale − min(brut × 20 %, ∞)
                          = brut − CNSS salariale − brut × 20 %
```

Abattement frais professionnels : **20 % du brut, non plafonné**
(`professionalExpensesDeduction() = ['rate' => 20.0, 'cap' => null]`),
appliqué par `PayrollCalculator::calculateSlip()`.

## 3. Contribution Nationale (CN)

**1,5 % sur la part du BRUT mensuel excédant 50 000 XOF** (seuil annuel
600 000 XOF) :

```
CN mensuelle = max(0, brut − 50 000) × 0,015
```

Calculée sur le brut réel via `CedeaoPayrollRules::calculateBracketTax()`
(portée par PayrollCalculator sur la ligne « Taxe de minimum fiscal »).

**Impôt total mensuel = ITSAS mensuel + CN mensuelle.**

## 4. CNSS — Cotisations sécurité sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS retraite salariale | 3,2 % | salarié | 1 647 315 XOF/mois |
| CNSS retraite patronale | 4,5 % | employeur | 1 647 315 XOF/mois |
| CNSS prestations familiales patronale | 5,75 % | employeur | 1 647 315 XOF/mois |
| CNSS risques professionnels patronale | 2,0 % | employeur | **non plafonné** (pilote) |

Codes : `CNSS_CI_RET_EMP`, `CNSS_CI_RET_PAT`, `CNSS_CI_FAM_PAT`,
`CNSS_CI_AT_PAT`.

## 5. SMIG

**75 000 XOF/mois** — `CedeaoPayrollRules::minimumWage()` pour CI.

## 6. Congés payés

2,2 j/mois de travail effectif (art. 25.1) + majoration d'ancienneté
+0,2 j/5 ans — 📝 à documenter/test.

## 7. Heures supplémentaires (art. 21)

| Plage hebdomadaire | Majoration |
|---|---|
| 40 – 48 h | +15 % |
| 48 – 54 h | +35 % |
| Nuit/dimanche > 54 h | +50 % |

`overtimeRateTiers()` CI : `[{8 h, 1.15}, {14 h, 1.35}, {∞, 1.50}]`.

## 8. Préavis (art. 18)

| Catégorie | < 5 ans | ≥ 5 ans |
|---|---|---|
| Ouvriers | 8 jours | 15 jours |
| Employés / Techniciens | 1 mois | 2 mois |
| Cadres | 3 mois | 3 mois |

⚠️ L'interface `noticePeriodDays(yearsOfService)` n'expose que l'ancienneté :
implémentation pilote au niveau **employé/technicien** (< 5 ans : 30 j ;
≥ 5 ans : 60 j) — ouvriers et cadres documentés ici, la catégorie du contrat
sera prise en compte dans un suivi.

## 9. 13ème mois

Pratique généralisée via conventions de branche (obligatoire dans la
plupart) → `thirteenthMonthMandatory() = true` pour CI ; PayrollCalculator
injecte la ligne en décembre (mécanisme ZONE-INFRA #1820, traitement
`fully_taxable`).

## 10. Jours fériés

Fixes : 1ᵉʳ janvier, Lundi de Pâques, 1ᵉʳ mai, Ascension, Lundi de
Pentecôte, 7 août, 15 août, 1ᵉʳ novembre, 15 novembre, 25 décembre + Aïd
el-Fitr, Aïd el-Adha, Maouloud (islamiques via table `islamic_calendar`).
Gestion dynamique via #1811/#1812.

## Procédure de mise à jour des taux

1. Valider les nouveaux taux avec un expert-comptable OHADA-CI.
2. Modifier les valeurs par défaut dans `CedeaoPayrollRules` ET/OU insérer
   de nouvelles lignes `tax_slabs` / `social_contributions` datées
   (`effective_from`) pour un changement de barème sans régression
   historique.
3. Mettre à jour ce fichier + les golden tests (`GoldenCiPayrollTest`).
4. Faire valider par l'équipe (`php artisan test --filter=Payroll`).
5. Passer `confidenceLevel()` de `pilot` à `production` une fois validé.
