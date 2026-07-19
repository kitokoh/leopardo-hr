# Plan 46 - Controles tenant mobile platform admin

## Objectif

Passer la fiche client super-admin mobile d'une vue de lecture a un cockpit operationnel minimal : modifier l'abonnement et les modules sans ouvrir le dashboard web.

## Livrables

- Lecture du catalogue SaaS via `GET /api/v1/platform/plans`.
- Bottom sheet `Modifier abonnement` :
  - choix du plan actif ;
  - statut `active`, `trial`, `suspended`, `expired` ;
  - note interne optionnelle ;
  - sauvegarde via `PATCH /api/v1/platform/companies/{company}/subscription`.
- Bottom sheet `Modifier modules` :
  - toggles par module connu ;
  - module `rh` verrouille actif ;
  - sauvegarde via `PATCH /api/v1/platform/companies/{company}/features`.
- Rafraichissement de la fiche client apres sauvegarde.
- Contrat mobile enrichi pour couvrir les deux actions d'edition.

## Validation attendue

- `validate-mobile-workflow-contracts.ps1` passe.
- `validate-mobile-plan29.ps1` passe.
- GitHub Actions analyse et build `leopardo_platform_admin`.
