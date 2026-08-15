# ISSUE 3152 — Config Firebase placeholder : échec visible au bootstrap

> Spec Kit mini-spec — issue #3152 (vague qa-expert5-2026-08-15).

## Constat

`google-services.json` / `GoogleService-Info.plist` versionnés contiennent des
clés factices (`AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000`,
`mobilesdk_app_id` à zéros, `REDACTED_GOOGLE_API_KEY`). `PushNotificationService`
avalait les échecs d'init en silence → push mortes sans aucun signal.

## Contexte (important)

Le stub est **volontaire** (mobile-apps-ci.yml, issue #1467) : il permet aux
forks/builds locaux de compiler sans secret. La CI restaure la vraie config
depuis le secret `GOOGLE_SERVICES_JSON` ; en local,
`dev-hub/tools/install-mobile-firebase-configs.ps1` fait le même travail.

## Décision

Ne PAS retirer le stub (il est requis pour les forks) mais rendre l'échec
**visible** : `_ensureFirebaseInitialized()` détecte la config placeholder
(`REPLACE` / `000000000000` / `AIzaSyREPLACE` dans l'erreur) et affiche un
message d'action clair au bootstrap, puis relance l'exception (le catch
appelant continue de logguer).

## Critères d'acceptation

1. Un build avec stub → message explicite « CONFIG PLACEHOLDER détectée » au bootstrap.
2. Un build avec vraie config → aucun changement de comportement.
3. `flutter analyze` leopardo_core : 0 erreur.
