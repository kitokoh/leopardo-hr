# Plan 56 - Platform admin mobile auth hardening

## Objectif

Verrouiller l'application mobile `leopardo_platform_admin` avant lancement :
elle doit toujours passer par une session super-admin reelle, gerer le 2FA
explicitement et permettre la creation client sans formulaire ambigu.

## Livraisons

- Session : `GET /platform/auth/me` n'est tente que si un token local existe.
- Login : le retour `202 TWO_FA_REQUIRED` affiche un etat 2FA clair au lieu d'une
  erreur generique de token manquant.
- Demo : le login mobile propose un remplissage du compte demo super-admin.
- Creation client : validation mobile des emails et du code pays ISO avant appel
  `POST /platform/companies`.

## Garde-fous

- Les routes plateforme restent exclusivement sous `/api/v1/platform/*`.
- Aucun token tenant employee/manager ne doit etre reutilise comme session
  platform admin.
- Les checks attendus restent `PlatformAuthTest`, `PlatformCompanyProvisioningTest`,
  `FrontendApiContractTest` et `mobile-apps-ci`.
