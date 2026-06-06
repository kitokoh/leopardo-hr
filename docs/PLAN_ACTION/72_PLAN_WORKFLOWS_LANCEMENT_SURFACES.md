# PLAN 72 - Workflows lancement toutes surfaces

## Objectif

Verrouiller les parcours critiques avant exposition marketing plus large :

- vitrine : CTA commerciaux, essai guide, demo, pricing, ressources ;
- web admin client : connexion, cockpit, clients, utilisateurs, paie ;
- mobile employee : login, pointage, absence, avance, paie, compte, notifications ;
- mobile manager : equipe, validations, absences, avances, paie, taches, horaires ;
- mobile platform admin : creation client, activation, pays/devise, abonnement, features ;
- kiosk : pointage biometrie-first, fallback identifiant/QR, offline sync.

Le but n'est pas de remplacer les tests fonctionnels existants. Le but est d'ajouter un garde transversal qui evite qu'un bouton, une route ou un endpoint critique disparaisse silencieusement.

## Lot 72.1 - Contrats lancement multi-surface

### Actions

- Ajouter un manifeste machine-readable des workflows de lancement.
- Couvrir les surfaces web vitrine, web admin, platform admin mobile et kiosk.
- Deleguer les controles mobiles existants aux validateurs Plan 28/29/runtime/release.
- Documenter la regle : toute nouvelle action visible de lancement doit declarer sa route, son endpoint et ses tokens UI/API.

### Criteres

- `dev-hub/tools/validate-launch-workflows.ps1 -SkipDelegates` passe localement vite.
- Le validateur complet peut etre lance en CI ou manuellement avant release.
- Les anciens liens `/auth/signup` restent interdits dans la vitrine.

## Lot 72.2 - Recette fonctionnelle exhaustive

### Actions restantes

- Ajouter des smokes API authentifies pour creation/activation entreprise, paie, pointage, absence, avance, notifications et kiosk.
- Publier un rapport par profil : employee, manager, super-admin platform, client web admin, visiteur vitrine, borne kiosk.
- Relier les resultats au `RELEASE_READINESS_GATE`.

## Lot 72.3 - UX commerciale et multilingue

### Actions restantes

- Continuer la suppression progressive des textes hardcodes selon `I18N_DEBT_REPORT_2026_06_06.md`.
- Prioriser vitrine, login, compte, checkout/essai, pricing et erreurs API visibles.
- Verifier la lisibilite RTL arabe sur les pages publiques et les ecrans mobiles critiques.

## Statut

Lot 72.1 livre. Le manifeste `dev-hub/tools/launch-workflow-contracts.json`, le validateur `dev-hub/tools/validate-launch-workflows.ps1`, le rapport `docs/validation/LAUNCH_WORKFLOW_CONTRACTS_2026_06_06.md` et le gate `release-readiness.ps1` couvrent maintenant les workflows visibles de lancement.
