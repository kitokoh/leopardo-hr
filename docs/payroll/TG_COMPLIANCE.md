# 🇹🇬 Référentiel de conformité paie — Togo (TG)

> **Issue #2121** — Référentiel légal versionné du moteur de paie togolais
> (CEDEAO/UEMOA). Passe `CedeaoPayrollRules` (instance TG) de « placeholder »
> à « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux CGI, CNSS, Code du travail).
> Sources : CGI Togo (Loi 2018-024 consolidée + LF 2023 art. 74), CNSS
> (Loi 67-12 / Code sécurité sociale 2011-006), OTR, Code du travail 2021,
> Décret n°2023-096/PR (AMU).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRPP (8 tranches annuelles progressives) | ✅ implémentée (pilot) | CGI art. 74 (LF 2023) | à valider expert |
| Abattement frais professionnels 28 % (fraction ≤ 10 M) | ✅ implémentée (pilot) | CGI art. 26 | à valider expert |
| Charges de famille 10 000/mois/pers. (max 6) | 📝 non modélisée (défaut 0) | CGI art. 72-73 | à valider expert |
| CNSS salariale 4 % (pensions) | ✅ implémentée (pilot) | CNSS / cleiss | à valider expert |
| CNSS patronale 17,5 % (12,5 pensions + 3 famille + 2 AT) | ✅ implémentée (pilot) | CNSS / cleiss | à valider expert |
| AMU salariale 5 % / patronale 5 % | 📝 non modélisée (suivi) | Décret 2023-096/PR | à valider expert |
| SMIG 52 500 XOF/mois (2023) | ✅ implémentée | Arrêté 2022 (SMIG/SMAG) | vérifié 2026-08-14 |
| HS (+15 % / +35 %) | ✅ implémentée (pilot, paliers OHADA) | Code du travail | à valider expert |
| Préavis (15 j / 1 m / 3 m selon catégorie) | ✅ implémentée (pilot, niveau employé) | Code du travail art. 74 | à valider expert |
| Jours fériés fixes TG | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques mobiles | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. IRPP — Impôt sur le Revenu des Personnes Physiques

**Barème ANNUEL progressif** (`CedeaoPayrollRules::defaultTaxSlabs()` pour TG —
CGI art. 74, Loi n°2022-022 portant loi de finances 2023, reconduit) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 900 000 | 0 % |
| 900 001 – 3 000 000 | 3 % |
| 3 000 001 – 6 000 000 | 10 % |
| 6 000 001 – 9 000 000 | 15 % |
| 9 000 001 – 12 000 000 | 20 % |
| 12 000 001 – 15 000 000 | 25 % |
| 15 000 001 – 20 000 000 | 30 % |
| > 20 000 000 | 35 % |

## 2. Assiette IRPP

```
assiette IRPP mensuelle = brut − CNSS salariale (4 %)
                          − abattement frais pro 28 % (fraction du revenu ≤ 10 M FCFA/an)
```

- CGI art. 26 : cotisations sociales déductibles (CNSS 4 % ; l'AMU 5 % salariale
  existe depuis le décret 2023-096/PR mais n'est **pas encore modélisée** — suivi).
- CGI art. 26 : abattement forfaitaire frais professionnels **28 %** sur la
  fraction du revenu n'excédant pas **10 000 000 FCFA/an** (équivalent mensuel :
  assiette plafonnée à 833 333,33 XOF/mois, déduction plafonnée à 233 333,33).
- CGI art. 72-73 : réduction charges de famille **10 000 XOF/mois/personne**
  (max 6 personnes) — **non modélisée** (le moteur ne porte pas encore les
  parts familiales ; défaut 0 = célibataire 1 part, comme la RICF CI #2117).

## 3. CNSS — Cotisations sécurité sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS pensions salariale | 4,0 % | salarié | **non plafonné** |
| CNSS pensions patronale | 12,5 % | employeur | **non plafonné** |
| CNSS prestations familiales patronale | 3,0 % | employeur | **non plafonné** |
| CNSS risques professionnels patronale | 2,0 % | employeur | **non plafonné** |

Total patronal **17,5 %**, total salarial **4 %** (cleiss 2026, assiette = totalité
des revenus versés, plancher SMIG). Codes : `CNSS_TG_VIE_EMP`, `CNSS_TG_VIE_PAT`,
`CNSS_TG_FAM_PAT`, `CNSS_TG_AT_PAT`.

## 4. SMIG

**52 500 XOF/mois** (depuis le 1er janvier 2023, 40 h/semaine) —
`CedeaoPayrollRules::minimumWage()` pour TG (remplace l'ancien 35 000 de 2012).

## 5. Heures supplémentaires

+15 % pour les 8 premières heures/semaine, +35 % au-delà (Code du travail
OHADA) — paliers `1.15` / `1.35`, seuil légal 40 h/semaine (défaut zone).

## 6. Fin de contrat

Préavis (Code du travail art. 74, matrice par catégorie) :

| Catégorie | Préavis |
|---|---|
| Ouvriers / employés | 1 mois (30 j) |
| Agents de maîtrise, cadres | 3 mois (90 j) |

Le moteur ne transmet pas la catégorie à `noticePeriodDays()` : approximation
pilote sur le niveau employé (30 j), matrice complète documentée ci-dessus — à
valider par expert-comptable (comme BF/ML/SN, #1829/#2123).

Indemnité de licenciement : défaut moteur générique 1 mois de base par année
d'ancienneté (F-08) — à valider expert. Solde de tout compte : congés payés
non pris + primes dues.

## 7. Arrondis

Conformes au contrat moteur (`docs/payroll/CALCULATION_CONTRACT.md`) :
impôt calculé sur l'assiette non arrondie, montants exposés arrondis à 2
décimales (demi au plus proche), net plancher à 0. La règle CGI art. 74
(arrondi du revenu au millier inférieur et de l'impôt à la dizaine
inférieure) est une particularité de la déclaration annuelle ; elle n'est pas
reproduite dans le moteur mensuel (même convention que BF/ML/CI).

## 8. Niveau de confiance et avertissement

- `confidenceLevel()` : `pilot` (jamais `production` sans fiche de validation
  experte signée — règle VIII constitution, registre `VALIDATION_EXPERTE.md`)
- Date de vérification : `null` (aucune validation experte à ce jour)
- Avertissement affiché : clé i18n générique `payroll.compliance_warning_pilot`
  (dérivée de `confidenceLevel()`, `AbstractCountryRules::complianceWarning()`)

## 9. Sources

- Code général des impôts togolais (Loi n°2018-024 consolidée) — art. 26
  (abattement 28 %), 72-73 (charges de famille), 74 (barème 8 tranches) :
  https://www.otr.tg/index.php/fr/impots/reglementations-fiscales/code-general-des-impots.html
- Loi n°2022-022 portant loi de finances 2023 (barème art. 74 reconduit) :
  https://assemblee-nationale.tg/wp-content/uploads/2023/12/LOI-de-FINANCES-loi-AN.pdf
- CNSS Togo — taux de cotisations (loi 67-12 / Code sécurité sociale
  2011-006) : https://cnss.tg/employeurs/cotisations-sociales/ et
  https://www.cleiss.fr/docs/cotisations/togo.html
- SMIG/SMAG 52 500 FCFA (1er janvier 2023) : https://www.republicoftogo.com/toutes-les-rubriques/social/revalorisation-du-smig
- Décret n°2023-096/PR du 4 octobre 2023 (AMU) : https://cnss.tg/cnss-media/2024/01/DECRET-2023-096-fixant-les-taux-de-cotisations-AMU.pdf
- Code du travail togolais 2021 (préavis art. 74) : https://www.lqdd.org/textes-de-loi/code-du-travail-togolais-2021/
- Vérifié le : 2026-08-14, par : agent d'implémentation (à confirmer par
  expert-comptable OHADA-Togo)

## 10. Golden tests

`api/tests/Feature/Payroll/Golden/GoldenTgPayrollTest.php` — cas calculés à la
main avec référence aux sections ci-dessus (issue #1938) : SMIG (net 0 IR),
cadre moyen 300 000 (IRPP tranche 3 %), haut salaire 800 000 (tranches
3 %/10 %/15 %), plafond abattement (brut 1 000 000 → déduction plafonnée
233 333,33), CNSS non plafonnée (brut élevé), palier HS +15 %.

> **Statut code (2026-08-26, issue #5623)** : `confidenceLevel()` de
> `CedeaoPayrollRules` retourne **pilot** pour ce pays (barèmes implémentés,
> non validés par un expert-comptable local). L'UI paie affiche désormais un
> badge/avertissement pour tout pays au niveau **placeholder** (ex. membres
> CEDEAO hors CI/BF/ML/TG, membres CEMAC hors CM/GA/CG) et exige une
> confirmation explicite avant validation d'un run placeholder.
