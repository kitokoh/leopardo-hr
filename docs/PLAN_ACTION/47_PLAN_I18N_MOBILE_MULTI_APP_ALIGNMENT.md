# Plan 47 - Alignement i18n mobile multi-app

## Objectif

Eviter que les traductions de Jules restent synchronisees uniquement vers l'ancien mobile alors que les apps de lancement utilisent `front/mobile_apps/leopardo_core`.

## Probleme corrige

Le workflow i18n surveillait `front/mobile/lib/l10n/**`, mais pas le core multi-app. Le script `sync-mobile.js` ecrivait en plus vers un ancien chemin racine `mobile/lib/l10n`, non suivi comme cible canonique de production. Cela pouvait laisser employee, manager et platform admin avec des ARB obsoletes.

## Livrables

- `sync-mobile.js` genere les ARB vers :
  - `front/mobile/lib/l10n` ;
  - `front/mobile_apps/leopardo_core/lib/l10n`.
- Le workflow `I18N Enterprise` surveille aussi `front/mobile_apps/leopardo_core/lib/l10n/**`.
- Le Plan 24 et les consignes AGENTS indiquent le chemin core mobile aux traducteurs.

## Validation attendue

- `node shared/i18n/validators/validate.js` passe.
- `node shared/i18n/sync/sync-mobile.js` passe.
- `git diff --exit-code` ne doit montrer aucune ARB oubliee apres synchronisation.
