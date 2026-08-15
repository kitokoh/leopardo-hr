# Mini-spécification — Issue #3272

## Objectif

Rendre accessibles aux super-admin les deux vues qui disposent déjà d’endpoints `/v1/admin/*`, sans ouvrir les autres routes qui dépendent réellement d’un contexte tenant.

## Correction

Les métadonnées `requiresTenant` sont retirées uniquement de `/fleet` et `/exports`. Le guard global continue de rediriger les routes tenant-only comme `/chat`, `/reports`, `/audit`, `/leaves`, `/contracts`, `/recruitment`, `/training`, `/webhooks` et `/predictions`.

## Critères d’acceptation

1. Un token super-admin peut atteindre `/fleet` et `/exports` sans redirection tenant.
2. Les dix routes dépendantes d’un tenant conservent `requiresTenant`.
3. Aucun endpoint métier ni permission backend n’est modifié.
4. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3272  
Branche : `fix/3272-unblock-admin-exports-fleet`  
Date : 2026-08-15
