# 🇨🇮 Référentiel de conformité paie — Côte d'Ivoire (CI)

> **Issue #1825** — Référentiel légal versionné du moteur de paie ivoirien
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` de « placeholder » à « pilot ».
> ⚠️ **À valider par un expert-comptable OHADA-CI avant passage à
> « production »** (taux CGI 2024, CNSS, Code du travail CI).
> Sources : CGI Côte d'Ivoire 2024 (art. 116-120), [guide officiel CNPS — Employeur](https://www.cnps.ci/employeur/), Code du travail CI. Le guide CNPS indique 70 000 FCFA/mois pour prestations familiales et AT/MP, contre 1 647 315 FCFA/mois pour la retraite.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| ITS unifié (6 tranches mensuelles, réforme 2024) | ✅ implémentée (pilot) | ord. 2023-718/719, CGI art. 119 bis | à valider expert |
| Contribution Nationale (1,5 %) | ❌ abolie (fusionnée ITS, #1918) | ord. 2023-718/719 | — |
| Abattement frais pro (20 %) | ❌ supprimé de la base ITS (réforme 2024, #1918) | ord. 2023-718/719 | — |
| CNSS retraite 3,2 % / 4,5 % (plaf. 1 647 315) | ✅ implémentée (pilot) | CNSS | à valider expert |
| CNSS famille patronale 5,75 % (plaf. 70 000) | ✅ implémentée (pilot) | Guide officiel CNPS Employeur | à valider expert |
| CNSS AT patronale 2,0 % (plaf. 70 000, taux sectoriel à confirmer) | ✅ implémentée (pilot) | Guide officiel CNPS Employeur | à valider expert |
| SMIG 75 000 XOF/mois | ✅ implémentée | — | à valider |
| 13ème mois (conventions de branche) | ✅ implémentée (pilot) | pratique généralisée | à valider expert |
| Congés (2,2 j/mois, +0,2 j/5 ans) | 📝 à documenter/test | Code travail CI art. 25.1 | — |
| HS (15 % / 35 % / 50 %) | ✅ implémentée (pilot) | Code travail CI art. 21 | à valider expert |
| Préavis (art. 18) | ✅ implémentée (pilot, niveau employé) | Code travail CI art. 18 | à valider expert |
| Jours fériés fixes CI | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. ITS — Impôt sur le Traitement et les Salaires (réforme 2024, #1918)

> ⚠️ **Réforme 2024 (ordonnance 2023-718/719, effet 01/01/2024)** : l'ancien
> système « ITSAS annuel + CN » (CGI art. 116-120 pré-réforme) est
> **supprimé et fusionné dans un ITS unique** calculé sur le **salaire brut
> mensuel**, sans abattement frais pro ni division par parts (CGI art.
> 119 bis). Le moteur CI implémentait l'ancien barème (0/2/21/24,5/29 %
> annuels, tranche > 10 M @ 29 % — taux ne correspondant à aucun barème
> publié) ; la migration #1918 remplace ce barème par l'ITS 2024.

**Barème MENSUEL** (implémenté dans `CedeaoPayrollRules::defaultTaxSlabs()`
pour le membre CI — CGI art. 119 bis, ord. 2023-718/719) :

| Tranche mensuelle (XOF) | Taux |
|---|---|
| 0 – 75 000 | 0 % |
| 75 001 – 240 000 | 16 % |
| 240 001 – 800 000 | 21 % |
| 800 001 – 2 400 000 | 24 % |
| 2 400 001 – 8 000 000 | 28 % |
| > 8 000 000 | 32 % |

`calculateIncomeTax()` applique le barème progressif mensuel directement sur
le **brut** (bornes inclusives, même mécanique que le moteur). La **RICF**
(réduction d'impôt pour charges de famille, art. 120 : 5 500–44 000 XOF/mois
selon le nombre de parts) n'est **pas encore appliquée** — les données
familiales (parts) ne sont pas portées par le moteur ; défaut 0
(célibataire, 1 part). À compléter quand les données employé le permettront.
⚠️ Pour CI, `calculateIncomeTax()` retourne **l'ITS seul** ; la CN n'existe
plus (abolie, §3).

## 2. Assiette ITS

```
assiette ITS mensuelle = BRUT (salaires bruts versés — art. 119 bis)
```

Plus d'abattement frais professionnels depuis la réforme 2024
(`professionalExpensesDeduction() = ['rate' => 0.0, 'cap' => null]`) et plus
de déduction de la CNSS salariale de la base ITS. La CNSS reste une
cotisation sociale distincte (§4).

## 3. Contribution Nationale (CN) — ABOLIE (réforme 2024, #1918)

**1,5 % sur la part du BRUT mensuel excédant 50 000 XOF** — **supprimée** :
l'ordonnance 2023-718/719 (effet 01/01/2024) la fusionne dans l'ITS unique.
`CedeaoPayrollRules::calculateBracketTax()` retourne **0** pour la CI
(aucune ligne « CN » au bulletin). Conservation de la référence historique :

```
CN mensuelle = max(0, brut − 50 000) × 0,015
```

Calculée sur le brut réel via `CedeaoPayrollRules::calculateBracketTax()`
(portée par PayrollCalculator sur la ligne « Taxe de minimum fiscal »).

**Avant réforme : impôt total mensuel = ITSAS annuel / 12 + CN mensuelle — remplacé depuis 2024 par l'ITS unique mensuel (aucune CN).**

## 4. CNSS — Cotisations sécurité sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS retraite salariale | 3,2 % | salarié | 1 647 315 XOF/mois |
| CNSS retraite patronale | 4,5 % | employeur | 1 647 315 XOF/mois |
| CNSS prestations familiales patronale | 5,75 % | employeur | **70 000 XOF/mois** |
| CNSS risques professionnels patronale | 2,0 % | employeur | **70 000 XOF/mois** (taux sectoriel à confirmer) |

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
