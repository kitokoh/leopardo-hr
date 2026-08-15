# Spec — Import de l’exception pays inconnue dans le test Payroll

## Contexte

`PayrollCalculatorUnitTest::test_get_rules_throws_for_unknown_country` attend `UnsupportedCountryRulesException`, mais le fichier de test ne déclare pas l’import de cette classe. PHP tente alors de résoudre `Tests\\Unit\\UnsupportedCountryRulesException`, qui n’existe pas.

## Objectif

Aligner le test avec l’exception métier réellement levée par `PayrollCalculator::getRules()`.

## Décision

Ajouter uniquement l’import `App\\Modules\\Payroll\\Domain\\Exceptions\\UnsupportedCountryRulesException`. Aucun changement de production ni modification du comportement du résolveur n’est nécessaire.

## Critères d’acceptation

1. Le test référence la classe d’exception métier complète via son import.
2. Le scénario pays inconnu conserve son assertion d’exception.
3. Le test ne résout plus une classe dans le namespace `Tests\\Unit`.
4. Aucun comportement runtime ni contrat API n’est modifié.
