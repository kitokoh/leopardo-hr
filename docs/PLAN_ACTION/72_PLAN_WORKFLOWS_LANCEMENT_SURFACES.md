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

### Actions

- Ajouter un smoke API par profil pour les parcours publics, employee, manager/RH, super-admin platform et kiosk.
- Couvrir les lectures critiques : auth, pointage du jour, mois complet, absences/conges, avances, paie, notifications, liste employes, cockpit manager, platform companies, plans, pays/devise et sante clients.
- Garder la creation entreprise de test derriere l'option explicite `-IncludePlatformProvisioning`.
- Publier un rapport par profil et relier le script au `RELEASE_READINESS_GATE`.

### Criteres

- `dev-hub/tools/launch-api-profile-smoke.ps1` passe sans token en validant les endpoints publics et en marquant les profils prives `SKIP`.
- Avec tokens proteges, les profils configures doivent retourner `PASS`.
- Aucun secret n'est commite.

### Statut

Lot 72.2 livre cote gouvernance et outillage. La preuve live complete avec tokens doit etre executee en environnement CI/ops protege avant ouverture marketing large.

## Lot 72.3 - UX commerciale et multilingue

### Actions restantes

- Continuer la suppression progressive des textes hardcodes selon `I18N_DEBT_REPORT_2026_06_06.md`.
- Prioriser vitrine, login, compte, checkout/essai, pricing et erreurs API visibles.
- Verifier la lisibilite RTL arabe sur les pages publiques et les ecrans mobiles critiques.

## Statut

Lots 72.1 et 72.2 livres. Le manifeste `dev-hub/tools/launch-workflow-contracts.json`, le validateur `dev-hub/tools/validate-launch-workflows.ps1`, le smoke `dev-hub/tools/launch-api-profile-smoke.ps1`, les rapports `docs/validation/LAUNCH_WORKFLOW_CONTRACTS_2026_06_06.md` / `docs/validation/LAUNCH_API_PROFILE_SMOKE_2026_06_06.md` et le gate `release-readiness.ps1` couvrent maintenant les workflows visibles de lancement et les smokes API par profil.
