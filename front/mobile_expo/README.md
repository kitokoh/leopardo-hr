# Applications mobiles non-Flutter (Expo / React Native)

Ce dossier est le pendant de `front/mobile_apps/` pour les applications mobiles
qui **ne sont pas** des projets Flutter.

## Pourquoi un dossier séparé

`front/mobile_apps/` est la source canonique des apps **Flutter** du dépôt, et
elle est gardée comme telle :

- `dev-hub/tools/check-mobile-layer-guard.sh` compare le disque au registre
  `dev-hub/tools/mobile-layer-registry.txt` (fail-closed) ;
- `dev-hub/tools/check-mobile-app-identities.sh` exige, pour **chaque** dossier
  `front/mobile_apps/leopardo_*`, un `applicationId` Android natif, un
  `PRODUCT_BUNDLE_IDENTIFIER` iOS, une entrée dans deux registres et trois
  matrices CI.

Un projet Expo n'a rien de tout cela : pas de `android/app/build.gradle.kts`,
pas de `ios/Runner.xcodeproj`, pas de `pubspec.yaml`. Le placer sous
`front/mobile_apps/` le faisait donc passer pour une app Flutter incomplète et
faisait échouer la garde d'identités — à raison, puisque la garde protège une
propriété réelle (deux apps ne doivent jamais partager un identifiant).

D'où la règle : **`front/mobile_apps/` = Flutter, `front/mobile_expo/` = Expo.**
Chaque dossier a sa propre garde, et aucune ne prétend juger l'autre.

## Doctrine « app non-Flutter »

1. Une app Expo vit sous `front/mobile_expo/<nom>/` et n'est **pas** ajoutée à
   `melos.yaml` (melos ne gère que les packages Dart).
2. Elle n'est **pas** ajoutée aux matrices de `mobile-apps-ci.yml`,
   `mobile-distribute.yml` ni `mobile-distribute-main.yml` : ces matrices
   compilent des APK Flutter via `flutter build apk` et Firebase App
   Distribution. Une app Expo se distribue par **EAS Build** (voir plus bas).
3. Elle porte son propre `package.json` et son propre `package-lock.json` — le
   dépôt n'utilise pas de workspaces npm (décision #7305).
4. Elle ne doit pas introduire de dépendance Flutter ni modifier `melos.yaml`.

## Applications présentes

- `leopardo_mobile_expo/` — application Expo/React Native **Leopardo Mobile** :
  socle RH (connexion + 2FA, pointage géolocalisé, absences, paie, avances,
  tâches, plannings, équipe, notifications, compte) **et** architecture des
  verticales alignée sur le web (41 modules, 9 verticales, cascade de gating
  identique à `front/web/src/lib/client-features.ts`) avec les actions
  d'écriture de chaque verticale.

## Compiler un APK (compte Expo gratuit)

```bash
cd front/mobile_expo/leopardo_mobile_expo
npm install
npm install -g eas-cli
eas login                 # compte gratuit sur expo.dev
eas build:configure       # renseigne le projectId dans app.json
EXPO_PUBLIC_API_URL=https://api.leopardo-rh.com/api/v1 \
  eas build -p android --profile preview
```

Le profil `preview` produit un **APK installable** (et non un AAB). L'URL de
l'API est **figée au moment du build** : sans `EXPO_PUBLIC_API_URL`, l'APK
pointera vers l'émulateur local et ne joindra aucune API.
