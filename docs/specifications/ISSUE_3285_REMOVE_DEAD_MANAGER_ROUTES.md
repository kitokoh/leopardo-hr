# Mini-spécification — Issue #3285

## Objectif

Éviter que l’app manager expose six deep-links GoRouter qui ne sont ni navigués par l’UI ni servis par le manifest backend.

## Correction

Les routes `/contracts`, `/training`, `/expenses`, `/ai-voice`, `/onboarding` et `/organigramme` sont retirées de `leopardo_manager/lib/app.dart`, ainsi que leurs imports d’écrans. Les providers et écrans restent dans le dépôt pour ne pas supprimer prématurément des fonctionnalités pouvant être réactivées via un manifest et une entrée UI.

## Critères d’acceptation

1. Les six routes ne sont plus déclarées dans le router manager.
2. Les imports correspondants sont supprimés.
3. Les routes manager réellement utilisées restent inchangées.
4. L’analyse statique et `git diff --check` passent.

## Trace Spec Kit

Issue : #3285  
Branche : `fix/3285-remove-dead-manager-routes`  
Date : 2026-08-15
