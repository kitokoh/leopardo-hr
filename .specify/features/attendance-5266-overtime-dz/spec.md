# Feature Specification: Heures supplémentaires DZ — règles légales + intégration paie (issue #5266)

**Feature Branch**: `mod/attendance/5266-overtime-dz`

**Created**: 2026-08-23

**Status**: Implémenté — PR ouverte (Closes #5266)

**Référentiel lié** : `docs/payroll/DZ_COMPLIANCE.md` (versionné) + spec `payroll-dz-100` (écart **E2** : arbitrage paliers 25 %/50 % vs 50 % unique).

**Sources vérifiées le 2026-08-23** :
- Loi 90-11 du 21/04/1990 relative aux relations de travail (version consolidée JORA, PDF ILO NATLEX `DZA-9557`) :
  - **Art. 32** : « Les heures supplémentaires effectuées donnent lieu au paiement d'une majoration qui ne peut en aucun cas être inférieure à 50 % du salaire horaire normal. »
  - **Art. 26** : durée légale hebdomadaire = **40 h** (le « 36 h » de l'énoncé d'issue est une erreur, aucune source ne le confirme).
  - **Art. 36** : « Le travailleur qui a travaillé un jour de repos légal a droit à un repos compensateur d'égale durée et bénéficie du droit de majoration des heures supplémentaires. »
- Références secondaires concordantes : ratib-rh.com (durée légale 40 h ; majoration min 50 %), mtess.gov.dz (art. 36).

---

## 1. Arbitrage E2 (décision)

| Question | Décision | Justification |
|---|---|---|
| Barème 25 % jusqu'à 10 h/mois puis 50 % (ancien §5 DZ_COMPLIANCE) vs 50 % unique ? | **50 % unique sur toutes les HS** (palier unique × 1,5) | Le texte (art. 32) impose un minimum de **50 %** sans barème ; le 25 % conventionnel était **sous le minimum légal** → risque de non-conformité. L'usage « 25 % » n'est pas confirmé par une convention collective. |
| Durée légale hebdomadaire | **40 h** (`overtimeThresholdWeeklyHours()` = 40, inchangé) | Art. 26 loi 90-11 ; « 36 h » de l'issue non sourcé. |
| Repos compensateur (art. 36) | **Documenté** (DZ_COMPLIANCE §5 + cette spec) ; suivi RH hors moteur de paie pour v1 | L'art. 36 ouvre un droit d'égale durée pour le travail un jour de **repos légal** ; la mécanique de paie reste la majoration ≥ 50 % (le moteur paie l'HS des jours travaillés, y compris repos légal via `AttendanceLog.overtime_hours`). |

## 2. Implémentation

- `PayrollCalculator::computeOvertimePay(base, hours, standardRateHours = 10, ?CountryRulesInterface $rules = null)` :
  - si `$rules->overtimeRateTiers()` non vide → **les paliers légaux du pays priment** (nouvelle méthode privée `computeOvertimePayByTiers()`, arrondi uniquement en sortie — précision #2685) ;
  - sinon → **fallback historique** (25 % jusqu'à `standardRateHours` puis 50 %) conservé pour les appels sans règles (mécanique générique F-05).
- `computeSlipValues()` passe `$rules` → **tout run de paie pays utilise ses paliers légaux** (DoD : le run DZ intègre les HS sans intervention manuelle). `PayrollCycleService::estimateOvertimePay()` reste une estimation dashboard 1,5× (placeholder documenté, non légal).
- `AlgeriaPayrollRules::overtimeRateTiers()` : docblock mis à jour (art. 32/36, sources), valeur inchangée `[['up_to_hours' => null, 'multiplier' => 1.5]]`.

## 3. Impact tests (golden DZ recalculés)

| Test | Avant (#2685 : 10 h @ 25 % puis 50 %) | Après (50 % unique) |
|---|---|---|
| `GoldenDzProrataOvertimeTest` 10 h / 15 h / 11 h | 4 327,01 / 6 923,21 / 4 846,25 | **5 192,41 / 7 788,61 / 5 711,65** |
| `GoldenDzFullSlipTest` 15 h (bulletin complet) | brut 66 923,21 / net 52 157,09 / coût 84 323,24 | **brut 67 788,61 / net 52 731,98 / coût 85 413,65** |
| `GoldenDzSlipIntegrationTest` (run F-20, 10 h) | gross 64 327,01 / net 50 432,43 / employer 16 725,02 / total 81 052,03 | **65 192,41 / 51 007,32 / 16 950,03 / 82 142,44** |
| Nouveau `GoldenDzOvertimeRulesTest` | — | paliers DZ + run complet avec ligne HS (DoD) |

- Les golden non-DZ testent leurs tiers directement (`overtimeRateTiers()`) — aucun impact.
- Les tests génériques (`PayrollWorkInputsTest`, `PayrollCalculatorRunEdgeTest`, `PayrollCalculatorCoverageTest`, `EstimationApiTest`…) ne vérifient que le pass-through des heures (pas de montant HS) ou appellent `computeOvertimePay` sans règles (fallback) → aucun impact.

## 4. DoD #5266

- [x] Un run de paie DZ intègre les HS **sans intervention manuelle** (pipeline F-20 `AttendanceLog.overtime_hours` → `collectWorkInputs` → `computeOvertimePay($rules)` → ligne bulletin « Heures supplémentaires ») — verrouillé par `GoldenDzSlipIntegrationTest` + `GoldenDzOvertimeRulesTest`.
- [x] Règles HS DZ : seuil hebdo 40 h (art. 26), majoration ≥ 50 % (art. 32), repos compensateur documenté (art. 36).
- [x] Golden tests DZ mis à jour + nouveaux cas.
- [x] CHANGELOG en tête d'[Unreleased] + doc DZ_COMPLIANCE §5.

---

*Spec — issue #5266 (Programme 100 %, wave W1). Source de vérité d'exécution : cette spec + `docs/payroll/DZ_COMPLIANCE.md`. Toute évolution de taux = procédure §3 de la spec `payroll-dz-100` (commit séparé sourcé + golden + CHANGELOG).*
