# 🇨🇲 Référentiel de conformité paie — Cameroun (CM)

> **Issue #1821** — Référentiel légal versionné du moteur de paie camerounais
> (CEMAC). Passe `CemacPayrollRules` de « placeholder » à « pilot ».
> ⚠️ **À valider par un expert-comptable camerounais avant passage à
> « production »** (taux CGI 2024, CNPS 2024, Code du travail loi 92/007).
> Sources : CGI Cameroun 2024 (art. 68), CNPS, Code du travail (loi 92/007).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRPP (4 tranches annuelles) | ✅ implémentée (pilot) | CGI 2024 art. 68 | à valider expert |
| Centimes additionnels (×1.10) | ✅ implémentée (pilot) | CGI 2024 | à valider expert |
| Abattement frais pro (30 %, plaf. 350 000 XAF/mois) | ✅ implémentée (pilot) | CGI 2024 art. 68 | à valider expert |
| CNPS vieillesse 4,2 % / 4,2 % (plaf. 750 000) | ✅ implémentée (pilot) | CNPS 2024 | à valider expert |
| CNPS famille patronale 7,0 % (plaf. 750 000) | ✅ implémentée (pilot) | CNPS 2024 | à valider expert |
| CNPS AT patronale 2,0 % (non plafonné) | ✅ implémentée (pilot) | CNPS 2024 — variable selon secteur | à valider expert |
| SMIG 41 875 XAF/mois | ✅ implémentée | décret 2014 | vérifier 2024 |
| Congés (1,5 / 2 / 2,5 j/mois) | 📝 à documenter/test | Code du travail (loi 92/007) | — |
| Heures sup (+20 % / +30 %) | ✅ implémentée (pilot) | Code du travail (loi 92/007) | à valider expert |
| Prime ancienneté (5 % → 15 %) | 📝 à documenter/test | Code du travail (loi 92/007) | — |
| Préavis (15 j / 1 m / 2 m / 3 m) | ✅ implémentée (pilot) | art. 34 loi 92/007 | à valider expert |
| Jours fériés fixes CM | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. IRPP — Impôt sur le revenu des personnes physiques (salaires)

**Barème ANNUEL** (implémenté dans `CemacPayrollRules::defaultTaxSlabs()` pour
le membre CM — CGI 2024, art. 68) :

| Tranche annuelle (XAF) | Taux |
|---|---|
| 0 – 2 000 000 | 10 % |
| 2 000 001 – 3 000 000 | 15 % |
| 3 000 001 – 5 000 000 | 25 % |
| > 5 000 000 | 35 % |

`calculateIncomeTax()` annualise l'assiette mensuelle (`× 12`), applique le
barème progressif (helper `AbstractCountryRules::calculateProgressiveTax()` —
bornes inclusives), puis ramène le résultat au mois.

## 2. Centimes additionnels

**10 % de l'IRPP calculé** (centimes communaux) : `IRPP final = IRPP × 1,10`.
Appliqué dans `CemacPayrollRules::calculateIncomeTax()` pour CM
(`round(($tax / $annualBasis) * 1.10, 2)`).

## 3. CNPS — Cotisations sécurité sociale (taux 2024)

| Cotisation | Taux | Type | Assiette | Plafond |
|---|---|---|---|---|
| CNPS vieillesse salariale | 4,2 % | salarié | brut | 750 000 XAF/mois |
| CNPS vieillesse patronale | 4,2 % | employeur | brut | 750 000 XAF/mois |
| CNPS prestations familiales patronale | 7,0 % | employeur | brut | 750 000 XAF/mois |
| CNPS risques professionnels patronale | 2,0 % | employeur | brut | **non plafonné** (pilote, variable selon secteur) |

Codes : `CNPS_CM_VIE_EMP`, `CNPS_CM_VIE_PAT`, `CNPS_CM_FAM_PAT`,
`CNPS_CM_AT_PAT`. La cotisation salariale totale = vieillesse salariale ;
la cotisation patronale totale = vieillesse + famille + AT.

## 4. Abattement frais professionnels

**30 % du brut, plafonné à 350 000 XAF/mois** (4 200 000 XAF/an) —
`CemacPayrollRules::professionalExpensesDeduction()` retourne
`['rate' => 30.0, 'cap' => 350000.0]` (plafond mensuel, l'énoncé d'issue
4 200 000 correspond au montant annualisé).

## 5. Assiette IRPP

```
assiette IRPP mensuelle = brut − CNPS salariale − min(brut × 30 %, 350 000 XAF)
```

Appliqué dans `PayrollCalculator::calculateSlip()` (l'abattement est lu sur
la règle pays via `professionalExpensesDeduction()` quand `rate > 0`).

## 6. SMIG

**41 875 XAF/mois** (décret 2014 — à vérifier 2024) —
`CemacPayrollRules::minimumWage()` pour CM.

## 7. Congés payés

1,5 j/mois pour les 5 premières années, 2 j/mois de la 6ᵉ à la 10ᵉ année,
2,5 j/mois après 10 ans (Code du travail, loi 92/007) — 📝 à documenter/test.

## 8. Préavis (Code du travail, art. 34)

| Ancienneté | Préavis |
|---|---|
| < 6 mois | 15 jours |
| 6 mois – 5 ans | 1 mois |
| 5 – 10 ans | 2 mois |
| > 10 ans | 3 mois |

Implémenté dans `CemacPayrollRules::noticePeriodDays()` (30/60/90 jours pour
les paliers ≥ 6 mois).

## 9. Heures supplémentaires

+20 % pour les 8 premières heures/semaine, +30 % au-delà
(`CemacPayrollRules::overtimeRateTiers()` — paliers `1.20` / `1.30`, seuil
légal 40 h/semaine).

## 10. Prime d'ancienneté

Obligatoire légalement : 5 % après 2 ans, +1 %/an, plafond 15 % — 📝 à
documenter/test.

## 11. Jours fériés

Fixes : 1ᵉʳ janvier, 11 février (fête nationale), 1ᵉʳ mai, 20 mai, 15 août,
25 décembre + fêtes islamiques mobiles (via table `islamic_calendar`,
issue #1812). La gestion dynamique des jours fériés par pays est couverte
par l'issue #1811 (CRUD admin + `working_days` dynamique).

## Procédure de mise à jour des taux

1. Valider les nouveaux taux avec un expert-comptable camerounais.
2. Modifier les valeurs par défaut dans `CemacPayrollRules` ET/OU insérer de
   nouvelles lignes `tax_slabs` / `social_contributions` datées
   (`effective_from`) pour un changement de barème sans régression historique.
3. Mettre à jour ce fichier + les golden tests (`GoldenCmPayrollTest`).
4. Faire valider par l'équipe (`php artisan test --filter=Payroll`).
5. Passer `confidenceLevel()` de `pilot` à `production` une fois validé.
