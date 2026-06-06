# Validation workflows lancement - 2026-06-06

## Perimetre

Validation du Plan 72 : routes, endpoints et boutons critiques pour les surfaces de lancement.

Surfaces couvertes par le nouveau manifeste :

- `front/web` : vitrine, essai guide, demo, pricing, ressources ;
- `front/admin-dashboard` : login, navigation protegee, clients, utilisateurs, paie ;
- `front/mobile_apps/leopardo_platform_admin` : creation client, activation, pays/devise, abonnement, features ;
- `front/zkteco-kiosk` : pointage offline-first, fallback QR/identifiant, sync kiosk.

Les apps employee/manager restent couvertes par `dev-hub/tools/mobile-workflow-contracts.json` et `validate-mobile-workflow-contracts.ps1`.

## Commandes executees

```powershell
powershell -ExecutionPolicy Bypass -File dev-hub\tools\validate-launch-workflows.ps1 -SkipDelegates
powershell -ExecutionPolicy Bypass -File dev-hub\tools\validate-mobile-workflow-contracts.ps1
powershell -ExecutionPolicy Bypass -File dev-hub\tools\release-readiness.ps1 -Strict
git diff --check
```

## Resultats

- `validate-launch-workflows.ps1 -SkipDelegates` : PASS.
- `validate-mobile-workflow-contracts.ps1` : PASS.
- `release-readiness.ps1 -Strict` : PASS, `27/27 checks passed`.
- `git diff --check` : PASS, uniquement avertissement CRLF Windows sur `release-readiness.ps1`.

## Decisions

- Tout nouveau bouton ou parcours critique visible avant lancement doit etre ajoute dans `dev-hub/tools/launch-workflow-contracts.json`.
- Les anciens liens vitrine `/auth/signup` restent interdits dans les sources applicatives ; le test E2E peut conserver ce token uniquement comme garde de non-regression.
- Le validateur complet peut deleguer aux gardes mobiles existants avant release ; le mode `-SkipDelegates` sert aux PR rapides qui ne touchent pas les apps mobiles.

## Risques restants

- Ce garde verifie la presence de routes/endpoints/tokens, pas le succes fonctionnel runtime avec un vrai backend.
- Les smokes authentifies par profil restent a automatiser dans le Lot 72.2.
