# Leopardo Mobile Apps

Ce dossier est la source canonique des applications mobiles **Flutter** de
lancement. Le mobile historique (`front/mobile/`) a ete retire du depot ; ces
apps sont desormais les seules applications mobiles Flutter actives.

Les applications mobiles **non-Flutter** (Expo / React Native) vivent dans
`front/mobile_expo/`. Elles ne sont volontairement pas listees ici : ce dossier
est garde par `check-mobile-layer-guard.sh` et `check-mobile-app-identities.sh`,
qui exigent pour chaque `leopardo_*` un `applicationId` Android natif, un bundle
id iOS, une entree dans deux registres et trois matrices CI. Un projet Expo n'a
rien de cela — il echouerait donc a raison.

## Doctrine « app non-Flutter »

1. Une app Expo vit sous `front/mobile_expo/<nom>/` et n'est **pas** ajoutee a
   `melos.yaml` (melos ne gere que les packages Dart).
2. Elle n'est **pas** ajoutee aux matrices de `mobile-apps-ci.yml`,
   `mobile-distribute.yml` ni `mobile-distribute-main.yml` : ces matrices
   compilent des APK Flutter via `flutter build apk` et Firebase App
   Distribution. Une app Expo se distribue par **EAS Build**.
3. Elle porte son propre `package.json` et son propre `package-lock.json` — le
   depot n'utilise pas de workspaces npm (decision #7305).
4. Elle n'introduit aucune dependance Flutter et ne modifie pas `melos.yaml`.

Applications presentes sous `front/mobile_expo/` :

### `leopardo_mobile_expo/` — Leopardo Mobile (usage interne)

Socle RH + architecture des verticales alignee sur le web + actions d'ecriture
des verticales. Build APK :

```bash
cd front/mobile_expo/leopardo_mobile_expo
npm install && npm install -g eas-cli
eas login && eas build:configure
EXPO_PUBLIC_API_URL=https://api.leopardo-rh.com/api/v1 \
  eas build -p android --profile preview
```

### `leopardo_public_expo/` — Leopardo grand public (clients et particuliers)

La surface **grand public** en Expo : une app, neuf verticales, un parcours
reel par verticale. Aucun compte, aucun jeton — toutes les routes appelees
sont publiques (`/public/*`).

| Verticale (code web) | Parcours dans l'app | Contrat appele |
|---|---|---|
| `restaurant` | Trouver un restaurant, composer un panier, commander, suivre | `GET /public/restaurants/{slug}`, `POST .../orders`, `GET .../orders/{ref}?secret=` |
| `commerce` | Parcourir les boutiques, panier, checkout, suivi | `GET /public/market/sellers`, `GET .../products`, `POST .../orders`, `GET .../orders/{ref}?token=` |
| `hospitality` | Fiche etablissement, disponibilites par dates, reservation | `GET /public/hospitality/properties/{slug}`, `GET .../availability`, `POST .../reservations` |
| `travel` | Rechercher un trajet inter-villes, reserver une place | `GET /public/travel/marketplace/cities`, `GET .../trips`, `POST .../bookings` |
| `mobility` | Choisir un operateur VTC, estimer, commander une course | `GET /public/vtc/marketplace/operators`, `POST .../estimate`, `POST .../rides` |
| `health` | Annuaire des pharmacies, recherche | `GET /public/discover?type=pharmacy` |
| `trade` | Fiche entreprise, catalogue produits, demande de contact | `GET /public/trade/companies/{slug}`, `GET .../products`, `POST /public/contact-requests` |
| `education` | Portail parents par jeton remis par l'ecole | `GET /edu-manager/portal/{token}` (jeton, pas d'annuaire public) |
| `fuel` | Ecran d'attente explicite | **aucun** — la verticale n'a pas de contrat public |

Les codes de verticale sont **identiques** a `VERTICALS` de
`front/web/src/lib/client-features.ts` (`commerce`, `mobility`, `health` — pas
`retail`/`vtc`/`pharmacy`), pour que la taxonomie soit la meme des deux cotes.

Build APK :

```bash
cd front/mobile_expo/leopardo_public_expo
npm install && npm install -g eas-cli
eas login && eas build:configure
EXPO_PUBLIC_API_URL=https://api.leopardo-rh.com/api/v1 \
  eas build -p android --profile preview
```

Le profil `preview` produit un APK installable. L'URL de l'API est figee au
build : sans `EXPO_PUBLIC_API_URL`, l'APK pointera vers l'emulateur local.

**Ce que l'app ne fait pas, et pourquoi.** Trois verticales n'ont pas de
contrat public complet dans l'API : `fuel` (aucune route publique), `education`
(portail a jeton, pas d'annuaire) et le devis structure de `trade` (pas de
route publique). L'app affiche alors le motif exact et propose l'action
disponible — elle ne simule jamais une fonction absente.

## Structure

- `leopardo_core/` : package Flutter partage. Il contient uniquement les briques communes : API client, stockage, i18n, theme, couleurs, typographie, widgets de base, modeles et providers core.
- `leopardo_employee/` : app mobile employe. Elle expose les parcours personnels : connexion, accueil employe, pointage, absences, avances, paie, notifications, documents et compte.
- `leopardo_manager/` : app mobile manager/RH. Elle conserve le perimetre complet du mobile actuel et prepare les routes des futurs ecrans manager.
- `leopardo_hr/` : app mobile RH dediee, issue d'un split de `leopardo_manager`. Integree a la matrice CI canonique `mobile-distribute.yml` pour le deploiement Firebase (voir `CHANGELOG.md`).
- `leopardo_platform_admin/` : app mobile super-admin plateforme. Elle consomme uniquement les API `/platform/*` pour piloter les tenants, creer une entreprise cliente, traiter les demandes clients et suivre les metriques globales.
- `leopardo_accounting/` : app mobile comptabilité (facturation, suivi des impayés). Intégrée à melos et à la CI (QA 2026-08-15, #2661) ; Android uniquement, son écran stats est encore un mock — chantier ouvert (périmètre documenté dans `leopardo_accounting/README.md`).
- `leopardo_marketing/` : app mobile marketing/communication (vitrine). Intégrée à melos et à la CI (QA 2026-08-15, #2661) ; son écran stats est encore un mock — chantier ouvert.
- `leopardo_travel_agent/` : app mobile agent/vendeur TravelAgency (TRAVEL-701 #6088 + TRAVEL-810 #6100) — vente guichet multi-passagers, encaissement cash, check-in QR, manifeste, caisse PDV. Basée sur `leopardo_core`, intégrée à melos et à la CI mobile.
- `leopardo_cameras/` : app mobile « Leopardo Caméras » (BC-19 DEVICE, #7426) — mur des caméras du tenant, visionnage direct via `GET /cameras/{id}/stream-token` (WebRTC MediaMTX, #7424), événements et alertes (#7427) avec cible de deep link `/events?focus=<id>`. Réservée aux responsables (module `cameras` + `api.manager`) ; basée sur `leopardo_core`, intégrée à melos et à la CI mobile.
- `leopardo_public/` : app mobile **grand public** (Couloir 16, #9102) — la **surface publique** Leopardo : trouver un commerce, voir sa fiche, lui parler, retrouver commandes et favoris. Ce n'est **ni une verticale ni une horizontale, c'est un canal** : aucune règle métier propre, aucun nouveau Bounded Context. Guard `user_api` (client), distinct de `sanctum` (employés) ; 4 onglets, 4 langues fr/en/ar(RTL)/tr, `minSdk 26` ; basée sur `leopardo_core`, intégrée à melos et à la CI mobile.
## Regles de contribution

- Toute modification partagee va dans `leopardo_core`.
- Toute modification d'ecran specifique va dans l'app concernee.
- L'app employe ne doit pas contenir de gestion d'equipe, validations manager, organigramme, approvals ou dashboard manager.
- L'app manager/RH conserve les ecrans complets et gere les differences internes via `employee.managerRole`.
- L'app platform admin ne contient aucun workflow tenant employe/manager : pas de pointage, absences, avances, equipe ou approvals RH.
- La differenciation par sous-role manager se fait dans les ecrans concernes, pas dans le router.
- `front/mobile/` (mobile historique) a ete retire du depot ; toutes les evolutions employee, manager/RH, HR et platform admin vont dans `front/mobile_apps/*`.

## CI et distribution

- `Mobile Apps CI - Flutter` (`mobile-apps-ci.yml`) analyse et teste les **12** packages : `leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_marketing`, `leopardo_accounting`, `leopardo_platform_admin`, `leopardo_travel_agent`, `leopardo_cameras`, `leopardo_public`, `leopardo_pos_retail` et `leopardo_pos_restaurant`.
- `Mobile - Build and Firebase Distribution` (`mobile-distribute.yml`) et `Mobile Distribute - Main` (`mobile-distribute-main.yml`) compilent et distribuent les APK Android vers Firebase App Distribution — **les 11 apps** sont dans les deux matrices (#9883 : six d'entre elles n'y figuraient pas, donc aucun APK n'était jamais produit).

La carte persona → app → état mesuré est dans `docs/mobile/CARTOGRAPHIE_APPS_MOBILES.md`.

## Garde-fous Plan 26

Le script canonique de validation de structure est :

```bash
pwsh ./dev-hub/tools/validate-mobile-apps-split.ps1
```

Sur Windows sans PowerShell 7 :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1
```

Il verifie notamment :

- aucun dossier manager (`team`, `approvals`, `organigramme`, `modules`) dans `leopardo_employee` ;
- aucun marqueur `isManager`, `canManageTeam`, `managerRole`, `isPrincipal` ou `isHr` dans l'app employe ;
- aucun import `package:leopardo_rh` dans les nouvelles apps ;
- aucun import `leopardo_employee` ou `leopardo_manager` depuis `leopardo_core` ;
- dependance `leopardo_core` presente dans les apps concernees ;
- routes manager preparees dans `leopardo_manager` ;
- identites App Store / Play Store distinctes pour `leopardo_employee`, `leopardo_manager` et `leopardo_hr` ;
- endpoints et routes critiques presents pour les workflows mobiles principaux ;
- absence de handlers UI vides sur les apps mobiles ;
- identifiants d'app uniques, identiques Android/iOS, et présence de chaque app dans
  les registres (couche, plateformes) et dans les matrices CI (analyse + distribution) —
  `dev-hub/tools/check-mobile-app-identities.sh`.

Si une evolution partagee est necessaire, la placer dans `leopardo_core`, puis consommer cette API depuis les apps concernees. Si une evolution ne concerne qu'un persona, la placer uniquement dans l'app correspondante (`leopardo_employee`, `leopardo_manager` ou `leopardo_hr`).

> **Chantier documenté (QA 2026-08-15, #2661)** : 13 fichiers repositories sont
> byte-identiques entre employee/manager/hr (ai_chat, ai_voice, cabinet,
> contracts, evaluations, expenses, notifications, training,
> vehicle_position, biometric_enrollment…). Ils devront migrer vers
> `leopardo_core` pour éviter la dérive de copies (ex. mojibake corrigé
> séparément dans chaque app).

## Identites store

| App | Android applicationId | iOS bundle id | Plateformes | Nom visible |
|---|---|---|---|---|
| `leopardo_public` | `com.leopardo.publicapp` | `com.leopardo.publicapp` | Android + iOS | Leopardo |
| `leopardo_employee` | `com.leopardo.employee` | `com.leopardo.employee` | Android + iOS | Leopardo Employee |
| `leopardo_manager` | `com.leopardo.manager` | `com.leopardo.manager` | Android + iOS | Leopardo Manager |
| `leopardo_hr` | `com.leopardo.rh` | `com.leopardo.rh` | Android + iOS | Leopardo RH |
| `leopardo_accounting` | `com.leopardo.accounting` | `com.leopardo.accounting` | Android + iOS | Leopardo Comptabilité |
| `leopardo_marketing` | `com.leopardo.marketing` | `com.leopardo.marketing` | Android + iOS | Leopardo Marketing |
| `leopardo_platform_admin` | `com.leopardo.platformadmin` | `com.leopardo.platformadmin` | Android + iOS | Leopardo Platform Admin |
| `leopardo_travel_agent` | `com.leopardo.travel_agent` | — | Android | Leopardo Travel Agent |
| `leopardo_cameras` | `com.leopardo.cameras` | — | Android | Leopardo Caméras |
| `leopardo_pos_retail` | `com.leopardo.posretail` | `com.leopardo.posretail` | Android + iOS | Leopardo POS Retail |
| `leopardo_pos_restaurant` | `com.leopardo.posrestaurant` | `com.leopardo.posrestaurant` | Android + iOS | Leopardo POS Restaurant |

Les identifiants sont **uniques et opposables** : `android` et `ios` d'une même app
sont identiques, et aucun identifiant n'est partagé entre deux apps. Le registre des
plateformes est `dev-hub/tools/mobile-platform-registry.txt` ; la vérification est
`dev-hub/tools/check-mobile-app-identities.sh` (#9883).

## Branding natif

Les assets canoniques du branding mobile sont generes directement dans chaque app :

- Android launcher : `android/app/src/main/res/mipmap-*/ic_launcher.png`
- Android adaptive icon : `android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml`
- Android splash : `android/app/src/main/res/mipmap-*/launch_image.png` et `drawable/launch_background.xml`
- Android notification : `android/app/src/main/res/drawable/ic_notification.xml`
- iOS App Store : `ios/Runner/Assets.xcassets/AppIcon.appiconset/`
- iOS splash : `ios/Runner/Assets.xcassets/LaunchImage.imageset/`

Ne pas remettre les icones Flutter par defaut. Pour changer l'identite visuelle, regenerer les familles d'assets employee, manager, HR et platform admin dans le meme lot afin de garder une coherence store.

Avant un upload public, le mode strict doit passer apres configuration des signatures release :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1 -StrictStores
```

## Validation attendue

Depuis un SDK Flutter compatible Dart 3.8+ :

```bash
pwsh ./dev-hub/tools/validate-mobile-apps-split.ps1
pwsh ./dev-hub/tools/validate-mobile-release-readiness.ps1

cd front/mobile_apps/leopardo_core
flutter pub get
flutter analyze

cd ../leopardo_employee
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1

cd ../leopardo_manager
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1

cd ../leopardo_hr
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1

cd ../leopardo_platform_admin
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
```
