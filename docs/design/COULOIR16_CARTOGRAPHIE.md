# Couloir 16 — Cartographie de l'existant (application grand public Leopardo)

- **Dépôt** : `kitokoh/leopardo-hr`
- **Branche de travail** : `feat/couloir16-public-app-lot-a` (supprimée après merge)
- **PR** : [#9101](https://github.com/kitokoh/leopardo-hr/pull/9101) — **mergée** dans `main` (`742229333`)
- **Date de cartographie** : 2026-11-02
- **Ville pilote** : `[VILLE]`, `[PAYS]` — catégorie moteur `restaurants`
- **Langues** : `fr`, `en`, `ar` (RTL), `tr`

---

## 0. Nature de l'application — la règle qui commande tout le reste

Ce n'est **ni une verticale ni une horizontale : c'est un canal** (surface publique).
Elle n'a aucune règle métier propre. Elle présente et orchestre des capacités qui
appartiennent déjà à d'autres Bounded Contexts.

Conséquence pratique : **aucun nouveau BC n'a été créé**, et aucune duplication de
messagerie, d'historique ou de compte n'a été introduite. Chaque brique réutilise
l'existant vérifié ci-dessous.

---

## 1. Ce qui existait déjà (vérifié au HEAD, pas supposé)

### 1.1 Découverte et fiche — ✅ fait

| Méthode | Route | Guard | Preuve |
|---|---|---|---|
| GET | `/api/v1/public/discover` | aucun (`throttle:shop-public`) | `routes/api.php:368` |
| GET | `/api/v1/public/restaurant/shop/menu` | jeton boutique signé | `routes/api.php:352` |
| POST | `/api/v1/public/restaurant/shop/orders` | jeton boutique signé | `routes/api.php:353` |
| GET | `/api/v1/public/restaurant/shop/orders/{reference}` | jeton boutique signé | `routes/api.php:354` |
| GET | `/p/{slug}` (SSR HTML) | aucun | `routes/modules/pages_public.php:27` |
| GET | `/api/v1/pages/by-host` | aucun | `routes/modules/pages_public.php:31` |

Le contrat est **strict** : `PublicDiscoverableProvider` interdit `id` interne,
`company_id`, stock et marge. Le `slug` **est** l'identifiant public.

`supportedActions()` est la **source unique des boutons** — aucun `BOOKING_TYPES`
codé en dur côté front. C'est ce qui garantit « aucun bouton mort ».

### 1.2 Contact et messagerie — ✅ fait

| Méthode | Route | Guard |
|---|---|---|
| POST | `/api/v1/public/contact-requests` | aucun (`throttle:shop-public`) |
| GET/POST | `/api/v1/public/conversations` | `auth:user_api` |
| GET | `/api/v1/public/conversations/{companyId}/{thread}` | `auth:user_api` |
| GET/POST | `.../{thread}/messages` | `auth:user_api` |

**Point d'architecture confirmé** : le tenant n'est **jamais** transmis par le
client pour démarrer un fil — il est résolu côté serveur depuis `target_slug`.
C'est le patron à réutiliser pour les activités.

### 1.3 Compte public — ✅ fait (partiel)

| Méthode | Route | Guard |
|---|---|---|
| POST | `/api/v1/user/register` | public (`throttle:auth-sensitive`) |
| POST | `/api/v1/user/login` | public |
| POST | `/api/v1/user/google-signin` | public |
| GET | `/api/v1/user/me` | `auth:user_api` |
| POST | `/api/v1/user/logout` | `auth:user_api` |
| POST | `/api/v1/auth/login-code/request` | public |
| POST | `/api/v1/auth/login-code/verify` | public |

Le guard `user_api` (provider `users`) est **distinct** de `sanctum` (employés) :
la séparation demandée par le propriétaire est déjà en place, pas à créer.

### 1.4 PWA web (V0) — ✅ fait

`front/web/src/app/decouvrir/` : page SSR indexable, `discovery-explorer.tsx`,
`contact-dialog.tsx`, `public-contact-cta.tsx`, `public-discovery-api.ts`.
`front/web/src/app/conversations/` : espace de conversation public.
`PWAProvider.tsx` : enregistrement du service worker, prompt d'installation,
background sync (`leopardo-sync`), gestion online/offline.

---

## 2. Ce qui a été livré dans cette session

### 2.1 M0 — architecture et contrat ✅

`docs/design/COULOIR16_PLAN.md` : inventaire réel des endpoints, modèle de données
du profil public et de l'index des activités (**références seulement**), contrat
OpenAPI, maquettes 360 px et RTL (`docs/design/couloir16-mockups/`).

### 2.2 M1 — app Flutter + seuil de catégories ✅

`front/mobile_apps/leopardo_public/` — 24 fichiers Dart :

| Zone | Fichiers |
|---|---|
| Coque et navigation | `core/navigation/app_shell.dart` (4 onglets, `IndexedStack`) |
| Accueil / découverte | `features/discovery/` (écran, providers, chips, tuiles, recherche) |
| Fiche commerce | `features/business_profile/` (écran, route, actions, horaires, menu) |
| Onglets secondaires | `features/tabs/secondary_tabs.dart` (Messages, Activités, Profil) |
| États | `core/widgets/state_widgets.dart` (squelette, vide, erreur, hors ligne) |
| i18n | `core/i18n/app_strings.dart` (fr/en/ar RTL/tr) |

Seuil de catégories éditable depuis le Superadmin :
`PlatformDiscoverySettingsController`, `PlatformSettingsRepository`,
migration `platform_settings`, vue `DiscoverySettingsView.vue`.

### 2.3 M1-bis — manifeste PWA de la surface publique ✅ (#9103)

**Le défaut trouvé.** `/decouvrir` déclarait le manifeste du **produit RH** :
`start_url: '/'`, catégories `business`/`productivity`, et quatre raccourcis
`/signup`, `/demo`, `/guides/rh-startup`, `/pricing`. Un visiteur qui installait
la PWA depuis un QR code de commerçant obtenait une icône ouvrant la page de
vente RH — exactement ce que la spécification interdit.

**Le correctif.**

| Fichier | Rôle |
|---|---|
| `front/web/src/app/decouvrir/manifest.webmanifest/route.ts` | manifeste dédié : `start_url`/`scope` = `/decouvrir`, catégories `food`/`shopping`/`travel`, 3 raccourcis publics, localisé ×4 avec `dir` correct |
| `front/web/src/app/decouvrir/layout.tsx` | la route publique déclare **son** manifeste via `metadata`, sans toucher au layout racine |
| `shared/i18n/locales/*.json` | bloc `publicApp` (4 locales), propagé par `sync-web.js` / `sync-mobile.js` |
| `front/web/src/app/decouvrir/__tests__/public-manifest.test.ts` | 8 tests : contrat public + **non-régression de `/manifest`** |

`/manifest` (produit RH) est **inchangé** — c'est l'objet du test de non-régression.

### 2.4 M1-ter — identité produit de l'app Flutter ✅ (#9110)

**Le défaut trouvé.** Le correctif M1-bis n'avait traité que la **PWA web**. Côté
Flutter, `front/mobile_apps/leopardo_public` — scaffoldée par copie d'une app
existante — avait gardé l'identité du **produit RH** :

| Fichier | Ce qu'il déclarait | Conséquence |
|---|---|---|
| `README.md` | « Leopardo RH — Application Mobile », identifiants de démo RH, `api.leopardo-rh.com` | un développeur qui ouvre l'app croit travailler sur le produit RH |
| `web/manifest.json` | `name`/`short_name` = `leopardo_rh`, `description` = « A new Flutter project. » | une PWA installée depuis l'app publique s'appelle `leopardo_rh` |
| `web/index.html` | `<title>leopardo_rh</title>`, description placeholder | idem côté navigateur |
| `ios/Runner/Info.plist` | `CFBundleName` = `leopardo_employee` | l'app iOS porte le nom d'une **troisième** app |

**Le correctif.**

| Fichier | Rôle |
|---|---|
| `front/mobile_apps/leopardo_public/README.md` | réécrit : identité publique, 4 onglets, 4 langues, guard `user_api`, 8 états, jalons M0→M4, portes (b)/(c) |
| `front/mobile_apps/leopardo_public/web/manifest.json` | `name`/`short_name`/`description`/`theme_color` propres à la surface publique, `categories: food/shopping/travel` |
| `front/mobile_apps/leopardo_public/web/index.html` | `<title>`, `meta description`, `apple-mobile-web-app-title` |
| `front/mobile_apps/leopardo_public/ios/Runner/Info.plist` | `CFBundleName` = `leopardo_public` |
| `melos.yaml` | `leopardo_public` déclarée — elle ne l'était **pas**, donc `melos analyze`/`melos test` ne la couvraient pas |
| `front/mobile_apps/README.md` | ligne de description + compteur « 9 packages » → 10 |
| `dev-hub/tools/check-mobile-app-identity.sh` | **garde** : refuse toute identité empruntée (manifeste, index.html, Info.plist, AndroidManifest) et toute app absente de `melos.yaml` |
| `dev-hub/tools/mobile-app-identity-baseline.txt` | dette préexistante figée (32 entrées, **aucune** pour `leopardo_public`) |
| `.github/workflows/mobile-apps-ci.yml` | le garde est branché dans `mobile-apps-guard` |

**Pourquoi une baseline et pas un simple échec.** Six autres apps portent déjà une
identité empruntée (dette antérieure à ce garde). Les faire échouer toutes
bloquerait `main` pour un défaut que ce lot ne prétend pas corriger. Le dépôt a
déjà tranché ce cas pour la duplication Flutter (#7652) : une baseline fige la
dette et refuse toute **nouvelle** entrée. La liste doit maigrir.

**Preuve du garde.** Cas nominal → exit 0 (10 apps, 0 nouvelle violation).
Test de mutation (réintroduction de `leopardo_rh` dans le manifeste public) →
exit 1 avec la violation nommée, puis restauration → exit 0.

---

## 3. Ce qui reste à faire — et pourquoi ce n'est pas fait

| Jalon | État | Blocage | Issue |
|---|---|---|---|
| **M2** — contact, OTP téléphone, messages, push | ⛔ **bloqué par conception** | porte (b) : attend **L1** (#9091, OTP fiable) et **L4** (#9094, convergence vers `users`) | [#9104](https://github.com/kitokoh/leopardo-hr/issues/9104) |
| **M3** — activités, favoris, historique | ✅ **livré** (#9108) | — | [#9105](https://github.com/kitokoh/leopardo-hr/issues/9105) |
| **M4** — hors ligne, perf, distribution | ⛔ **bloqué par conception** | porte (c) : dépense de compte développeur | [#9106](https://github.com/kitokoh/leopardo-hr/issues/9106) |

> **Rectification (2026-11-03, M1-ter #9110).** Cette table annonçait M3 comme
> « manquant ». C'était vrai à la date de la cartographie, plus au HEAD : M3 a
> été livré par la PR #9108 (commit `d3db45f48`) — `public_activity_index`,
> `public_favorites`, `public_search_history`, `PublicActivityService`,
> `PublicActivityLocator`, les routes `/user/activities`, `/user/favorites`,
> `/user/search-history` et l'onglet Flutter correspondant. La ligne est
> corrigée plutôt que laissée fausse : une cartographie périmée fait refaire un
> travail déjà fait.

### 3.1 Détail des manques vérifiés

| Manque | Preuve du manque | Impact |
|---|---|---|
| **OTP par téléphone** | `LoginCodeController:55` valide `email` uniquement | parcours critique « Contacter » non couvert |
| **Jetons de notification publics** | `device_tokens` vit en schéma **tenant** ; aucune route publique | push M2 bloqué |
| **Action `booking`** | `RestaurantDiscoverableProvider::supportedActions()` → `['contact','order']` | **correct** : pas de contrat public, donc pas de bouton |

> **Livré depuis (M3, #9108)** : favoris, activités et historique de recherche
> existent désormais sous `auth:user_api` — `GET /user/activities`,
> `GET|POST /user/favorites`, `DELETE /user/favorites/{type}/{slug}`,
> `GET|POST|DELETE /user/search-history`. L'onglet Activités n'est plus vide.
>
> **Livré depuis (M1, #9102)** : le seuil de catégories est éditable depuis le
> Superadmin (`GET|PUT /admin/platform/discovery/settings`) et
> `GET /public/discovery/categories` expose `visible` par catégorie. La ligne
> « catégories par ville » ci-dessus est donc résolue côté `establishment_type`.

### 3.2 Dette préexistante constatée (hors périmètre couloir 16)

`mobile-apps-ci` échoue sur `main` **depuis le 2026-10-28** : les 6 jobs
`Desktop verify … (macos-latest)` cassent sur un conflit Swift Package Manager
dans `leopardo_core` —

```
'firebase_messaging-17.0.1' depends on 'firebase-ios-sdk' 13.1.1
'google_sign_in_ios-6.3.3' depends on 'googlesignin-ios' 9.0.0..<10.0.0
```

Ce n'est **pas** causé par le couloir 16 (aucun fichier iOS touché), et les
4 checks requis par la protection de `main` sont verts. À traiter séparément.

---

## 4. Portes respectées

| Porte | Règle | État |
|---|---|---|
| **(b)** | M2 : PR ouvertes, **jamais mergées** tant que L1 et L4 ne sont pas sur `main` | ✅ M2 non démarrée |
| **(c)** | Toute dépense nouvelle (compte développeur de magasin) : accord explicite | ✅ aucun compte créé |

---

## 5. Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| `tsc --noEmit` (front/web) | ✅ |
| `eslint src/app/decouvrir src/app/manifest` | ✅ |
| `jest src/app/decouvrir` | ✅ 31/31 |
| `check-i18n-catalog-parity.sh` | ✅ parité 4 langues |
| `shared/i18n/validators/validate.js` | ✅ `I18N_VALIDATION_OK` |
| `check-i18n-diff.js` | ✅ aucune chaîne en dur ajoutée |
| Checks requis de `main` (4/4) | ✅ verts après merge |
| `next build` | compile ✅ puis tué par l'OOM du sandbox (exit 137) — la CI fait foi |

---

## 6. Liens

- **PR mergée** : https://github.com/kitokoh/leopardo-hr/pull/9101
- **Commit de merge** : `742229333`
- **Issues** : [#9102](https://github.com/kitokoh/leopardo-hr/issues/9102) (M1) ·
  [#9103](https://github.com/kitokoh/leopardo-hr/issues/9103) (M1-bis) ·
  [#9104](https://github.com/kitokoh/leopardo-hr/issues/9104) (M2) ·
  [#9105](https://github.com/kitokoh/leopardo-hr/issues/9105) (M3) ·
  [#9106](https://github.com/kitokoh/leopardo-hr/issues/9106) (M4)
- **Dépendances du couloir Identité** : [#9091](https://github.com/kitokoh/leopardo-hr/issues/9091) (L1) ·
  [#9094](https://github.com/kitokoh/leopardo-hr/issues/9094) (L4)
