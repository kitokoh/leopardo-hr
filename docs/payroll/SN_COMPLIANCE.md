# 🇸🇳 Référentiel de conformité paie — Sénégal (SN)

> **Issue #1827** — Référentiel légal versionné du moteur de paie sénégalais.
> `SenegalPayrollRules` passe de « pilot » vers prêt pour « production » :
> TRIMF, CFCE, régime cadres IPRES T2, plafonds, abattement frais pro.
> ⚠️ **Toutes les valeurs sont à valider par un expert-comptable sénégalais
> avant passage à « production »** (confidenceLevel() reste `pilot`).
> Sources : CGI Sénégal, IPRES, CSS, Code du travail.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (barème annuel 6 tranches) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| TRIMF (6 tranches forfaitaires) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| CFCE 3 % patronal | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| IPRES T1 5,6 % / 8,4 % (plaf. 432 000) | ✅ implémentée (pilot) | IPRES | à valider expert |
| IPRES T2 cadres 2,4 % / 3,6 % (tranche 432k-2 160k) | ✅ implémentée (pilot) | IPRES | à valider expert |
| CSS famille patronale 3 % | ✅ implémentée (pilot) | CSS | à valider expert |
| CSS AT patronale 1 % | ✅ implémentée (pilot) | CSS — variable selon secteur | à valider expert |
| Abattement frais pro 30 % (non plafonné) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| SMIG 58 900 XOF/mois | ✅ implémentée | — | à valider |
| Congés (2,1 j/mois = 25,2 j/an, +1 j/5 ans) | 📝 à documenter/test | Code du travail | — |
| Préavis (8 j ouvriers / 1 m employés / 3 m cadres) | ✅ implémentée (pilot, niveau employé) | Code du travail | à valider expert |
| Jours fériés fixes SN | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques (Korité, Tabaski, Gamou, Taamhrit) | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. IR — Impôt sur le revenu (salaires)

**Barème ANNUEL** (`SenegalPayrollRules::defaultTaxSlabs()`) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 630 000 | 0 % |
| 630 001 – 1 500 000 | 20 % |
| 1 500 001 – 4 000 000 | 30 % |
| 4 000 001 – 8 000 000 | 35 % |
| 8 000 001 – 13 500 000 | 37 % |
| > 13 500 000 | 40 % |

## 2. Assiette IR

```
assiette IR mensuelle = brut − cotisations salariales (IPRES T1+T2)
                        − abattement frais pro (30 % du brut, non plafonné)
```

## 3. TRIMF — Taxe Représentative des Impôts du Minimum Fiscal

Taxe forfaitaire mensuelle retenue sur le salarié
(`calculateBracketTax()`, portée dans le bulletin par PayrollCalculator sur
la ligne « Taxe de minimum fiscal ») :

| Tranche brut mensuel (XOF) | TRIMF mensuel |
|---|---|
| 0 – 25 000 | 900 |
| 25 001 – 75 000 | 2 700 |
| 75 001 – 150 000 | 5 400 |
| 150 001 – 350 000 | 9 000 |
| 350 001 – 700 000 | 18 000 |
| > 700 000 | 36 000 |

⚠️ Le mécanisme légal « le salarié paie le plus élevé de IR / TRIMF »
(max(IR, TRIMF)) est un raffinement à valider avec l'expert SN — dans le
périmètre de l'issue #1827, les deux lignes coexistent dans le bulletin.

## 4. IPRES — Régime Général T1

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| IPRES salariale | 5,6 % | salarié | 432 000 XOF/mois |
| IPRES patronale | 8,4 % | employeur | 432 000 XOF/mois |

## 4bis. IPRES — Régime Cadres T2

Sur la tranche **432 001 – 2 160 000 XOF/mois** (salaires au-delà du plafond
T1, hypothèse pilote : brut > 432 000 ⇒ régime cadres) :

| Cotisation | Taux | Type |
|---|---|---|
| IPRES cadres salariale (T2) | 2,4 % | salarié |
| IPRES cadres patronale (T2) | 3,6 % | employeur |

## 5. CFCE — Contribution Forfaitaire à la Charge de l'Employeur

**3 % sur la masse salariale brute** (charge patronale uniquement, non
plafonnée) — `CFCE_SN_PAT`.

## 6. CSS — Caisse de Sécurité Sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Prestations familiales patronale | 3,0 % | employeur | aucun |
| Accidents du travail patronale | 1,0 % | employeur | aucun (pilote, variable) |

## 7. Abattement frais professionnels

**30 % du brut, non plafonné** — `professionalExpensesDeduction() =
['rate' => 30.0, 'cap' => null]`.

## 8. Préavis

| Catégorie | Préavis |
|---|---|
| Ouvriers | 8 jours |
| Employés / Techniciens | 1 mois |
| Cadres | 3 mois |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — ouvriers et cadres documentés ici, la
catégorie du contrat sera prise en compte dans un suivi.

## 9. Congés payés

2,1 j/mois = 25,2 j/an, majoration +1 j/5 ans d'ancienneté — 📝 à
documenter/test.

## 10. Jours fériés

Fixes : 1ᵉʳ janvier, 4 avril, 1ᵉʳ mai, 15 août, 1ᵉʳ novembre, 25 décembre +
fêtes islamiques mobiles (Korité, Tabaski, Gamou, Taamhrit) via table
`islamic_calendar` (#1812). Gestion dynamique via #1811.

## Procédure de mise à jour des taux

1. Valider les nouveaux taux avec un expert-comptable sénégalais.
2. Modifier les valeurs par défaut dans `SenegalPayrollRules` ET/OU insérer
   de nouvelles lignes `tax_slabs` / `social_contributions` datées
   (`effective_from`) pour un changement de barème sans régression.
3. Mettre à jour ce fichier + les golden tests (`GoldenSnPayrollTest`).
4. Faire valider par l'équipe (`php artisan test --filter=Payroll`).
5. Passer `confidenceLevel()` de `pilot` à `production` une fois validé.
