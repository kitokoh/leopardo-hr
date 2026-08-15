# Mini-spécification — Issue #3240

## Objectif

Supprimer le squelette `api/app/Modules/Training/` non référencé, afin d’éviter une fausse source de vérité et du bruit dans l’analyse PHPStan. Les endpoints Training actifs restent ceux du module HR et ne sont pas modifiés.

## Constat

Les Actions, DTO, contrat, exceptions, FormRequests et provider du module Training n’étaient référencés par aucun contrôleur, route ou service actif. Seul le provider vide était enregistré dans `bootstrap/providers.php`.

## Correction

- Retirer l’enregistrement et l’import de `TrainingServiceProvider`.
- Supprimer le squelette non routé.
- Ne toucher ni aux routes Training actives du module HR, ni aux tables, ni aux contrats publics exposés.

## Critères d’acceptation

1. `api/app/Modules/Training/` n’existe plus.
2. `bootstrap/providers.php` ne référence plus `TrainingServiceProvider`.
3. Les routes et contrôleurs HR Training existants restent inchangés.
4. Aucun import `App\\Modules\\Training` ne subsiste dans `api`.
5. Les fichiers PHP modifiés passent `php -l` et `git diff --check`.

## Plan de retour arrière

Réversion du commit, qui restaure le provider et les fichiers supprimés sans migration de données.

## Trace Spec Kit

Issue : #3240  
Branche : `chore/3240-remove-dead-training-skeleton`  
Date : 2026-08-15
