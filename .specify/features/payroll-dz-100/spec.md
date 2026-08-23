# Feature Specification: Paie DZ 100 % — audit légal + verrouillage du périmètre (issue #5240)

**Feature Branch**: `mod/payroll/5240-dz-legal-audit`

**Created**: 2026-08-22

**Status**: Draft — en attente de validation fondateur + expert comptable DZ (DoD)

**Référentiel lié** : `docs/payroll/DZ_COMPLIANCE.md` (versionné, cœur validé expert comptable DZ le 2026-08-08).

**Sources vérifiées le 2026-08-22** :
- Moteur : `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/AlgeriaPayrollRules.php`
- Calculateur : `api/app/Modules/Payroll/Infrastructure/Services/PayrollCalculator.php`
- Référentiel : `docs/payroll/DZ_COMPLIANCE.md`
- Golden tests : `api/tests/Feature/Payroll/Golden/GoldenDz*.php`

---

## 1. Inventaire règle → texte légal → implémentation → statut

| # | Règle | Texte légal | Implémentation | Statut 2026-08-22 |
|---|---|---|---|---|
| R1 | SMIG / SNA 20 000 DZD/mois | SMIG légal (CNAS/loi) | `AlgeriaPayrollRules::minimumWage()` | ✅ validé expert (08/08) |
| R2 | IRG barème mensuel (0 / 23 / 27 / 30 / 33 / 35 %) | CIDTA art. 104 — LF 2022 (réforme IRG) | `defaultTaxSlabs()` | ✅ validé expert (08/08) |
| R3 | IRG abattement 40 % (plancher 12 000/an, plafond 18 000/an), annualisé | CIDTA art. 104 bis | `calculateIncomeTax()` (annualisation `annualBasis=12` puis abattement plancher/plafond) | ✅ validé expert (08/08) + golden `GoldenDzIrgBracketsTest` |
| R4 | CNAS salariale 9 % | CNAS | `socialContributions()` → `CNAS_EMP` 9 % | ✅ validé expert (08/08) |
| R5 | CNAS patronale 26 % | CNAS | `socialContributions()` → `CNAS_PAT` 26 % | ✅ validé expert (08/08) |
| R6 | Plafond d'assiette CNAS | aucun texte d'application documenté | `cap => null` (aucun) | ⚠️ « aucun — à confirmer » (`DZ_COMPLIANCE.md` §2) → **écart E1** |
| R7 | Assurance chômage CNAC : 1 % patron + 0,5 % salarié | décret législatif n° 94-11 art. 94-188 ; décrets exécutifs n° 22-70 (10/02/2022) et n° 26-87 (21/01/2026) | **inclus dans les agrégats CNAS** (pas de lignes AC séparées — anti-double cotisation, `DZ_COMPLIANCE.md` §7) | ✅ documenté + verrouillé golden `GoldenDzEndOfContractFullTest` (#1943) |
| R8 | Retraite (régime général) | CNAS | incluse dans CNAS patronale 26 % | ⚠️ ventilation des branches non isolée → à documenter (E6 / #5247) |
| R9 | Durée légale hebdomadaire 40 h | loi 90-11 art. 26 | `overtimeThresholdWeeklyHours()` = 40 | ✅ |
| R10 | Heures sup : majoration ≥ 50 % | loi 90-11 art. 33 | palier unique illimité × 1,5 (`overtimeRateTiers()`) | ⚠️ **écart E2** : `DZ_COMPLIANCE.md` §5 mentionne l'usage « 25 % jusqu'à 10 h/mois, 50 % au-delà » (seuil conventionnel à confirmer) vs moteur 50 % unique → arbitrage #5266 |
| R11 | Repos hebdo vendredi + samedi | loi 90-11 art. 27 modifiée | `weeklyRestDays()` = `[5, 6]` | ✅ |
| R12 | Jours fériés : fixes (1er jan, 1er mai, 5 juil, 1er nov) + fêtes islamiques mobiles | seed `PublicHolidaySeeder` #2255 + calendrier islamique #1812 | `publicHolidaysSource()` | ✅ |
| R13 | Congés payés 2,5 j/mois ; indemnité 1/10ᵉ vs maintien | loi 90-11 (Code du travail) | calculs congés + indemnités | ✅ golden `GoldenDzLeaveIndemnityTest` / `GoldenDzLeaveIndemnityRealDataTest` (#1537) |
| R14 | Prorata mois incomplet, absence, congés sans solde | usage + loi 90-11 | `PayrollCalculator::computeWorkedDays()` + `aggregateWorkInputs()` | ✅ golden F-05 (`GoldenDzProrataOvertimeTest`, `GoldenDzFullSlipTest`, `GoldenDzSlipIntegrationTest`) |
| R15 | Préavis licenciement : 1 mois (< 10 ans) / 2 mois (≥ 10 ans) | loi 90-11 art. 73-4 et 98 (usage dominant — pas de durée légale ferme) | `noticePeriodDays()` = 22 / 44 jours **ouvrés** | ⚠️ `pilot` — validation expert requise (E6) |
| R16 | Indemnité de licenciement 1 mois/an | loi 90-11 art. 72 | `severanceMonthsPerYear()` = 1.0 | ⚠️ `pilot` — **plafond légal non appliqué** (E6) |
| R17 | Primes exonérées IRG | barèmes LF (exonérations) | ❌ non gérées par le calculateur (la prime « fixe soumise » existe via la structure salariale — `GoldenDzSlipIntegrationTest` ; l'**exonération** est un écart) | ❌ → **écart E3** |
| R18 | 13ᵉ mois | convention collective (usage) | mécanisme générique `ThirteenthMonth` présent dans le moteur ; **règle DZ non verrouillée** | ⚠️ → **écart E4** |
| R19 | Maladie / arrêt de travail | loi 90-11 (maladie ordinaire / professionnelle) | ❌ non implémenté | ❌ → **écart E5** |
| R20 | Démission / fin de contrat | loi 90-11 | solde de tout compte + préavis + indemnité | ✅ golden `GoldenDzEndOfContractRulesTest`, `GoldenDzEndOfContractFullTest`, `GoldenDzFinalSettlementTest` (hors plafonds → E6) |

## 2. Inventaire des écarts (barèmes 2026 + complétions du moteur)

| Écart | Détail | Issue de complétion |
|---|---|---|
| **E1** | Plafond d'assiette CNAS : « aucun » non confirmé | #5241 |
| **E2** | Heures sup : arbitrage paliers 25 %/50 % vs 50 % unique (règles légales DZ) | #5266 |
| **E3** | Primes exonérées IRG : mécanique d'exonération à ajouter | #5241 |
| **E4** | 13ᵉ mois : verrouiller la règle DZ sur le mécanisme générique + golden | #5241 |
| **E5** | Maladie / arrêt : règles d'indemnisation à implémenter | #5241 (+ #5245 pour le flux absence → paie) |
| **E6** | Préavis, indemnité, plafonds : validation expert comptable (`pilot` → `production`) | #5247 (docs légales + recette pilote) |

## 3. Verrouillage du périmètre légal DZ v1

- **Règles ✅** : périmètre v1 **verrouillé** — cœur (SMIG, IRG + abattement, CNAS 9/26 %) validé expert 2026-08-08 ; mécaniques (congés, prorata, HS, fin de contrat) verrouillées par golden tests.
- **Règles ⚠️ `pilot`** : périmètre v1 **conditionnel** — R15/R16 bloquantes pour la paie réelle tant que la validation expert (E6) n'est pas consignée (`confidenceLevel()` → `production`).
- **Écarts E1-E5** : **hors périmètre v1** — portés par les issues de complétion (§2). Règle d'or (#5149) : une modification de taux = commit séparé sourcé + référentiel + golden + CHANGELOG.

## 4. Validation (DoD #5240)

- [ ] **Revue fondateur** : statuts du §1 et écarts du §2 acceptés.
- [ ] **Revue expert comptable DZ** : R15/R16 (préavis, indemnité, plafond CNAS — E1/E6).
- [ ] **Indexation** : lien vers cette spec en tête de `docs/payroll/DZ_COMPLIANCE.md` (fait dans cette PR).
- [ ] **Chaque écart référencé dans une issue de complétion** : fait (§2 → #5241, #5244, #5245, #5247, #5266).

---

*Spec d'audit — issue #5240 (Programme 100 %, wave W1 Payroll DZ). Source de vérité d'exécution : cette spec + `docs/payroll/DZ_COMPLIANCE.md`. Toute évolution de règle = procédure §3.*
