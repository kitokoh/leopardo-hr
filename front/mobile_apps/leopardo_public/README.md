# Leopardo — application grand public (Couloir 16)

Application mobile de la **surface publique** Leopardo : trouver un commerce autour de
soi, voir sa fiche, lui parler, retrouver ses commandes et ses favoris.

> **Ce n'est ni une verticale ni une horizontale : c'est un canal.**
> Cette application n'a **aucune règle métier propre**. Elle présente et orchestre des
> capacités qui appartiennent déjà à d'autres Bounded Contexts (Identité, BC-13
> Restaurant, Shared/Discovery). Conséquence directe : **aucun nouveau Bounded Context
> n'est créé ici**, et aucune messagerie, aucun historique, aucun compte n'est dupliqué.

## Ce que l'application fait

| Onglet | Contenu | Source serveur |
|---|---|---|
| **Accueil** | recherche toujours visible, catégories dynamiques, bascule carte/liste, « ouvert maintenant » | `GET /api/v1/public/discover`, `GET /api/v1/public/discovery/categories` |
| **Messages** | conversations avec les commerces | `/api/v1/public/conversations` (guard `user_api`) |
| **Activités** | commandes, favoris, historique de recherche | `/api/v1/user/activities`, `/api/v1/user/favorites`, `/api/v1/user/search-history` |
| **Profil** | langue, consentement, compte | `/api/v1/user/me` (guard `user_api`) |

### Les boutons de la fiche ne sont jamais codés en dur

`Contacter` / `Commander` / `Réserver` sont construits **uniquement** à partir de
`supportedActions()` renvoyé par le serveur. Un bouton n'existe que si le commerçant
déclare la capacité **et** qu'une destination existe. C'est ce qui garantit qu'il n'y a
jamais de bouton mort — et c'est pourquoi `Réserver` n'apparaît pas pour les restaurants
aujourd'hui : aucun contrat public de réservation n'existe encore.

## Architecture

- **Framework** : Flutter 3.x, `minSdk 26` (contrainte `sentry_flutter` ^10, #8774).
- **État** : `flutter_riverpod` 3.x — providers + repositories, pas de Bloc.
- **Navigation** : `go_router` avec `StatefulShellRoute` + `IndexedStack` (l'état d'un
  onglet survit au changement d'onglet).
- **Base partagée** : `leopardo_core` (client API, thème, couleurs, typographie).
  Aucune dépendance métier (pas de géofencing, pas de paie, pas de caméras) n'entre ici.
- **Jetons** : `flutter_secure_storage` uniquement — Keystore Android / Keychain iOS,
  jamais une préférence en clair.

### Langues

`fr`, `en`, `ar` (**RTL**), `tr`. Le catalogue est **local** à l'application
(`lib/core/i18n/app_strings.dart`) et non les ARB de `leopardo_core` : les ~7 000 clés du
core sont le vocabulaire RH/Business OS, et les importer ferait ressembler cette
application à un logiciel RH avec des boutons ajoutés. Les clés sont **stables** ;
l'éditeur de messages du Superadmin édite les textes, jamais les clés.

### Les 8 états obligatoires

Squelette de chargement · vide · aucun résultat · erreur · hors ligne (bandeau + cache) ·
position refusée · introuvable (404) · succès. Ils vivent dans
`lib/core/widgets/state_widgets.dart` et sont couverts par `test/states_test.dart`.

## Environnements

L'URL d'API n'est **pas** résolue ici : `leopardo_core`'s `ApiClient.resolveBaseUrl()`
s'en charge, avec un comportement **fail-closed en release** (un build release sans
`--dart-define=API_BASE_URL=…` lève au lieu de retomber sur un backend de dev — #4524 /
#7963). Ce fichier ne duplique pas cette logique.

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 \
            --dart-define=PILOT_CITY=<ville pilote>
```

`PILOT_CITY` est **vide par défaut** : l'application ne prétend pas couvrir une ville
qu'elle ne connaît pas.

## Démarrage

```bash
cd front/mobile_apps/leopardo_public
flutter pub get
flutter analyze
flutter test
```

## État des jalons (Couloir 16)

| Jalon | Contenu | État |
|---|---|---|
| **M0** | dossier d'architecture et contrat | ✅ livré (#9101) |
| **M0-bis** | modèle de données du profil public | ✅ livré (#9109) |
| **M1** | app Flutter, seuil de catégories éditable | ✅ livré (#9102) |
| **M1-bis** | manifeste PWA propre à la surface publique | ✅ livré (#9103) |
| **M1-ter** | identité produit de cette application | ✅ livré (#9110) |
| **M2** | OTP téléphone, messages, push | ⛔ **bloqué — porte (b)** : attend L1 (#9091) et L4 (#9094) |
| **M3** | activités, favoris, historique | ✅ livré (#9108) |
| **M4** | hors ligne, performance, distribution | ⛔ **bloqué — porte (c)** : dépense de compte développeur |

## Portes

- **(b)** M2 n'est pas démarré : l'OTP par téléphone dépend de la fiabilisation de
  l'envoi SMS (L1) et de la convergence du grand public vers `users` (L4).
- **(c)** Aucun compte développeur de magasin n'est créé : c'est une dépense, elle exige
  un accord explicite.

## Documentation

- `docs/design/COULOIR16_PLAN.md` — architecture et contrat du couloir.
- `docs/design/COULOIR16_CARTOGRAPHIE.md` — ce qui existait, ce qui est livré, ce qui reste.
- `docs/design/PUBLIC_PROFILE_DATA_MODEL.md` — modèle de données du profil public.
- `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md` — contrat d'API du compte public.
