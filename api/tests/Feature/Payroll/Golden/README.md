# Golden tests paie — Programme FOCUS (F-03)

Les **golden tests** sont la référence de conformité du moteur de paie : chaque cas est **calculé à la main** (tableur/manuel), avec la référence légale documentée dans `docs/payroll/DZ_COMPLIANCE.md`, puis verrouillé en dur dans le test.

## Règles d'or

1. **Jamais de calcul dupliqué** : la valeur attendue est un nombre en dur, pas une reformulation de l'algorithme.
2. **Référence légale** : chaque test cite la section du `docs/payroll/*_COMPLIANCE.md` du pays (et donc la loi/décret).
3. **Un cas par famille** : IRG, CNAS, prorata, heures sup, absences, congés, avances, fins de contrat, régularisations.
4. **Toute modification de taux** = mise à jour simultanée du référentiel + du golden test + du CHANGELOG (procédure `*_COMPLIANCE.md`).
5. **Mécanique générique ≠ valeur légale (issue #1938)** : le prorata (F-05), la mécanique horaire des HS et les DÉFAUTS de fin de contrat (`noticePeriodDays()`/`severanceMonthsPerYear()` par défaut) sont testés UNE SEULE FOIS dans `GoldenGenericEngineTest` — jamais dans les suites pays. Une suite pays ne verrouille que des valeurs LÉGALES sourcées (taux, plafonds, tranches, paliers HS, préavis) calculées à la main ; un défaut hérité du moteur ne doit jamais y figurer comme s'il était légal.

## Cas couverts

| Fichier | Famille | Cas |
|---|---|---|
| `GoldenDzPayrollTest.php` | IRG + CNAS + net (DZ) | SMIG 20k, 60k, 350k — voir doc §1-§2 |

## Objectif (métrique FOCUS)

≥ **40 cas golden** (M+3). Le comptage est exposé par `dev-hub/tools/payroll-golden-report.sh`.

## Ajouter un cas

1. Créer le fichier dans ce dossier (famille dédiée).
2. Calculer la valeur attendue à la main, citer la référence.
3. Lancer : `php artisan test --filter=Golden` (ou via le pipeline `payroll-ci.yml`).
4. Mettre à jour `DZ_COMPLIANCE.md` si la règle n'y figure pas encore.
