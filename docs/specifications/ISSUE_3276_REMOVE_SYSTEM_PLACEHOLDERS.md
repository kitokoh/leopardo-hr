# Mini-spécification — Issue #3276

## Objectif

Éviter de présenter dans SystemView six panneaux permanents qui n’ont ni endpoint ni plan d’alimentation.

## Correction

Les sections « Métriques Temps Réel », « Outils de Test API », « Utilisation des Ressources », « Sécurité », « Sauvegardes » et « Configuration Plateforme » sont supprimées. Les cartes globales, le Health Check réel, l’observabilité des queues et l’observabilité des notifications restent affichés.

## Critères d’acceptation

1. Aucun des six placeholders n’est rendu dans SystemView.
2. Les composants adossés à des données réelles restent inchangés.
3. Les routes et endpoints existants ne sont pas modifiés.
4. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3276  
Branche : `fix/3276-remove-system-placeholders`  
Date : 2026-08-15
