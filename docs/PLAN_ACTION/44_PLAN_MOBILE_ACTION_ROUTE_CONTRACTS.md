# Plan 44 - Contrats actions/routes mobile

## Objectif

Verrouiller les boutons critiques des trois apps mobiles afin qu'une action visible ne pointe pas vers une route absente ou vers une API hors persona.

## Probleme corrige

Le garde mobile principal couvrait les workflows employee et manager, mais pas encore `leopardo_platform_admin`. Une regression sur le cockpit super-admin mobile pouvait donc casser une action `/platform/*` sans etre detectee par le contrat commun.

## Livrables

- Ajout de `platform_admin` dans `dev-hub/tools/mobile-workflow-contracts.json`.
- Couverture des workflows :
  - login/logout plateforme ;
  - dashboard metrics ;
  - liste et creation entreprise ;
  - demandes clients avec approbation/refus.
- Interdiction explicite des routes tenant employee/manager dans l'app platform admin.
- Support des routers non standards via `routerFile`.
- Support des routes Dart declarees avec guillemets simples ou doubles.

## Validation attendue

- `dev-hub/tools/validate-mobile-workflow-contracts.ps1` passe.
- `validate-mobile-plan29.ps1` reste vert via GitHub Actions.
- Toute future route super-admin mobile doit etre ajoutee au contrat avant merge.
