# Mini-spec — Issue #3152

## Problème

Les stubs Firebase versionnés des 4 apps mobiles dérivaient en silence (audit
2026-08-15, expert QA) :

- `google-services.json` ×4 : `package_name` **tous** copiés depuis l'app
  employee (`com.leopardo.employee`) au lieu de l'identité réelle de chaque app
  (manager `com.leopardo.manager`, hr `com.leopardo.rh`, platform admin
  `com.leopardo.platformadmin`).
- `GoogleService-Info.plist` ×4 : `BUNDLE_ID` idem + `API_KEY =
  REDACTED_GOOGLE_API_KEY`.
- Aucune garde CI ne détectait ni le mauvais package ni la réintroduction d'une
  vraie clé dans l'arbre versionné (les vraies clés ne doivent exister que via
  le secret `GOOGLE_SERVICES_JSON` / `install-mobile-firebase-configs.ps1`).

Conséquence : un build sans restauration du secret embarque un app id d'une
autre app → push FCM silencieusement inopérant, et `mobile-distribute.yml`
refuse/compare des app ids incohérents.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `package_name` de chaque stub Android | = identité réelle de l'app (namespace gradle) |
| `BUNDLE_ID` de chaque stub iOS | = `PRODUCT_BUNDLE_IDENTIFIER` réel |
| Clé `AIza…` non-stub dans un fichier versionné | CI rouge (fuite potentielle) |
| Stub attendu absent | CI rouge (build local cassé) |
| `bash dev-hub/tools/check-mobile-firebase-configs.sh` | exit 0 |
| Workflow `Architecture Quality` (hygiene-guards) | vert |

## Correctif

- Stubs corrigés : `package_name` / `BUNDLE_ID` alignés sur l'identité réelle
  des 4 apps (employee, manager, rh, platformadmin).
- Nouvelle garde `dev-hub/tools/check-mobile-firebase-configs.sh` câblée dans
  `architecture-check.yml` (job hygiene-guards, PR + push main) : vérifie
  package/BUNDLE_ID, autorise exclusivement les valeurs de stub
  (`1:000000000000:android:0000000000000000000000`,
  `REDACTED_GOOGLE_API_KEY`, `AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000`) et
  refuse toute vraie clé.

## Validation

Garde locale `exit 0` ; `bash -n` OK ; CI `Architecture Quality` (hygiene-guards)
en garde sur PR.

Closes #3152
