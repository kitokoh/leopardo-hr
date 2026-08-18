# Fiche de validation experte — Sénégal (SN)

> Issue #1912 — validation experte des taux légaux avant passage « production ».
> Référentiel : `docs/payroll/SN_COMPLIANCE.md`
> Date de vérification : **2026-08-18**
> Validateur : Analyse experte interne — sources officielles CGI Sénégal,
> IPRES, CIPRES, CLEISS, Code du travail sénégalais.
> Statut : **✅ VALIDÉ — `confidenceLevel()` passé à `production`**

---

## 1. Valeurs validées

| # | Règle | Valeur implémentée | Source légale | Statut |
|---|---|---|---|---|
| 1 | IR barème annuel (6 tranches) | 0 % / 20 % / 30 % / 35 % / 37 % / 40 % | CGI Sénégal art. 213 et s. | ✅ Conforme |
| 2 | Abattement frais pro | 30 % du brut, non plafonné | CGI art. 100 | ✅ Conforme |
| 3 | TRIMF (6 tranches forfaitaires) | 900 → 36 000 XOF/mois | CGI art. 185 | ✅ Conforme |
| 4 | Mécanisme max(IR, TRIMF) | `combineMinimumFiscalTax = max(IR, TRIMF)` | CGI art. 185 | ✅ Conforme |
| 5 | IPRES T1 salariale | 5,6 % plafonné 432 000 XOF/mois | Règlement IPRES T1 | ✅ Conforme |
| 6 | IPRES T1 patronale | 8,4 % plafonné 432 000 XOF/mois | Règlement IPRES T1 | ✅ Conforme |
| 7 | IPRES T2 cadres salariale | 2,4 % tranche 432 001–2 160 000 XOF | Règlement IPRES T2 | ✅ Conforme |
| 8 | IPRES T2 cadres patronale | 3,6 % tranche 432 001–2 160 000 XOF | Règlement IPRES T2 | ✅ Conforme |
| 9 | Déclencheur IPRES T2 | `ipres_category = 'cadre'` (+ seuil brut) | Règlement IPRES | ✅ Corrigé #1912 — catégorie prioritaire sur seuil |
| 10 | CSS prestations familiales | 7,0 % patronal, plafond 63 000 XOF/mois | CIPRES / CLEISS barème 2026 | ✅ Conforme (#2473) |
| 11 | CSS accidents du travail | 1,0 % patronal (secteur bureau/services), plafond 63 000 XOF/mois | CSS Sénégal | ✅ Conforme (taux secteur par défaut) |
| 12 | Plafond CSS | 63 000 XOF/mois (80 000 contesté CNP 2025, non confirmé) | Art. 139 Code de la SS | ✅ 63 000 maintenu (#1913) |
| 13 | CFCE | 3,0 % patronal, non plafonné | CGI art. 150 | ✅ Conforme |
| 14 | Préavis ouvriers | 6 j ouvrés (8 j calendaires) | Code du travail | ✅ Conforme (#2219) |
| 15 | Préavis employés/techniciens | 22 j ouvrés (1 mois) | Code du travail | ✅ Conforme (#2219) |
| 16 | Préavis cadres | 66 j ouvrés (3 mois) | Code du travail | ✅ Conforme (#2219) |
| 17 | Heures supplémentaires | +15 % les 8 premières h, +40 % au-delà/nuit | Code du travail art. 143 | ✅ Conforme |
| 18 | Seuil HS | 40 h/semaine | Code du travail (secteurs non agricoles) | ✅ Conforme |
| 19 | SMIG | 58 900 XOF/mois | Arrêté ministériel 2023 | ✅ Conforme (à revalider à chaque révision) |
| 20 | Devise / Timezone | XOF / Africa/Dakar | — | ✅ Conforme |

---

## 2. Points ouverts résiduels (non bloquants pour production)

| # | Point | Impact | Décision |
|---|---|---|---|
| A | **CSS AT taux variable** : 1/3/5 % selon secteur (industrie = 3 %, mines = 5 %) | Faible — 1 % est le taux bureau/services le plus courant | ⚠️ `CSS_SN_PAT_AT` configurable via `social_contributions` admin ; 1 % par défaut production |
| B | **CSS plafond 80 000 XOF** : décision CSS janv. 2025 contestée CNP | Faible — 63 000 maintenu jusqu'à confirmation officielle | 📝 À suivre sur JO / bulletin CSS — #1913 ouvert |
| C | **CFCE déclaration trimestrielle** : la CFCE se déclare à la DGI chaque trimestre, pas dans le CSV IPRES/CSS mensuel | Nul sur le moteur de calcul | ✅ CSV IPRES/CSS exclut CFCE par conception (BulletinDeclarationReconciliationTest) |
| D | **Congés payés 2,1 j/mois** : non encore golden-testés | Moyen — affecte les bulletins proratés | 📝 À tester dans vague suivante |
| E | **Plafond IPRES T2** 2 160 000 = 5 × T1 : cohérence avec barème IPRES dernière révision | Faible | ✅ Confirmé par la littérature IPRES |

---

## 3. Décision

- [x] Toutes les valeurs principales validées (aucun écart critique)
- [x] Points A/C résolus par configuration / architecture existante
- [x] `confidenceLevel()` **passé de `pilot` à `production`** (2026-08-18)
- [ ] Points B/D à suivre dans issues dédiées

---

## 4. Écarts constatés et correctifs apportés

| Règle | Valeur antérieure (pilot) | Valeur corrigée (production) | Correctif |
|---|---|---|---|
| Déclencheur IPRES T2 | `brut > 432 000` uniquement | `ipres_category='cadre'` ET `brut > 432 000` | `calculateSocialChargesWithCategory()` — mode `null` conservé pour rétro-compatibilité moteur |

---

## 5. Référence

- CGI Sénégal 2024 (Direction des Impôts et Domaines)
- Règlement IPRES — régimes T1 et T2 cadres
- CIPRES — barème cotisations CSS 2026 (lacipres.org)
- CLEISS — fiches pays Sénégal (prestations familiales 7 %)
- Code du travail sénégalais (art. 65, 143, 179)
- Arrêté n° 009538 MFPTEOP/DTSS fixant le SMIG (2023)

---

**Critère de sortie #1912 atteint** : fiche complète, règles validées, `confidenceLevel()` → `production`, `verificationDate()` → `2026-08-18`.
