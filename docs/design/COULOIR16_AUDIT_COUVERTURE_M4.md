# Couloir 16 — Audit de couverture des cas d'utilisation et des profils

- **Dépôt** : `kitokoh/leopardo-hr`
- **Branche** : `feat/couloir16-audit-couverture-m4`
- **Base** : `main` @ `1f745b08f`
- **Date** : 12 novembre 2026
- **Nature** : audit + comblement des trous de test. **Aucun écran, aucun schéma, aucune route créés.**

---

## 0. Plan de compréhension (5 lignes)

1. **La source de vérité est écrite, pas supposée** : `PUBLIC_ACCOUNT_API_CONTRACT.md` (§2.1–2.6), `PUBLIC_ACCOUNT_CONNECTION_MATRIX.md` (8 cas), `COULOIR16_PLAN.md` (§1, §4), `PUBLIC_PROFILE_DATA_MODEL.md` (§3, §7).
2. **Le contrat décrit 6 opérations de compte** ; les routes existent toutes (`routes/modules/user.php:24-66`), mais **3 n'avaient aucun test** — vérifié par recherche plein texte des chemins dans `tests/` : 0 occurrence.
3. **Un trou de test sur `refresh` ou `DELETE /account` n'est pas cosmétique** : ce sont les deux opérations les plus destructrices du cycle de vie (fenêtre de réutilisation de jeton ; suppression irréversible ou sans ré-authentification).
4. **Deux manques de fond ont été trouvés** et sont **hors périmètre de ce lot** : les routes PWA `/activites` et `/profil` n'existent pas alors que la navigation basse y renvoie, et la table `public_profiles` n'existe pas.
5. **Rien n'est déclaré couvert sans exécution** : les 19 tests ajoutés ont été lancés, 19 passed / 65 assertions.

---

## 1. Cas d'utilisation du contrat de compte — état réel

Source : `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md` §2.1–2.6.

| # | Cas d'utilisation | Route | Test | État |
|---|---|---|---|---|
| 1 | Inscription e-mail | `POST /user/register` | `Feature/Auth/RegisterLoginFlowTest.php` | ✅ couvert |
| 2 | Connexion mot de passe | `POST /user/login` | `Feature/Auth/RegisterLoginFlowTest.php` | ✅ couvert |
| 3 | Connexion Google | `POST /user/google-signin` | `Feature/Auth/AuthSelfRegistrationTest.php` | ✅ couvert |
| 4 | OTP téléphone — demande | `POST /user/login-code/phone/request` | `Feature/Auth/PublicPhoneOtpTest.php` (14) | ✅ couvert **et exécuté** |
| 5 | OTP téléphone — vérification | `POST /user/login-code/phone/verify` | `Feature/Auth/PublicPhoneOtpTest.php` | ✅ couvert **et exécuté** |
| 6 | Repli SMS → e-mail | — | `Feature/Auth/LoginCodeSmsChannelTest.php` (16) | ✅ couvert **et exécuté** |
| 7 | **Rotation de jeton** | `POST /user/refresh` | **`PublicAccountLifecycleTest` (3 tests)** | ✅ **comblé par ce lot** |
| 8 | Révocation courante | `POST /user/logout` | `Feature/User/UserAuthTest.php` | ✅ couvert |
| 9 | Révocation multi-appareils | `POST /user/logout-all` | `PublicLogoutAllTest.php` (5) | ✅ couvert **et exécuté** |
| 10 | **Suppression de compte (RGPD)** | `DELETE /user/account` | **`PublicAccountLifecycleTest` (5 tests)** | ✅ **comblé par ce lot** |
| 11 | **Jetons d'appareil (push)** | `POST`/`DELETE /user/device-tokens` | **`PublicDeviceTokenTest` (10 tests)** | ✅ **comblé par ce lot** |
| 12 | Profil — lecture | `GET /user/me` | `Feature/User/UserAuthTest.php` | ✅ couvert |
| 13 | Profil — mise à jour | `PATCH /user/profile` | `Feature/User/UserAuthTest.php` | ✅ couvert |
| 14 | Changement de mot de passe | `POST /user/change-password` | `Feature/User/UserAuthTest.php` | ✅ couvert |
| 15 | Statuts personnels | `PATCH /user/personal-statuses` | `Feature/User/CompanyIntegrationRequestTest.php` | ✅ couvert |
| 16 | **Export de données (RGPD)** | *aucune route* | *aucun test* | ❌ **absent — voir §4** |

**Preuve du trou avant ce lot** (recherche plein texte dans `tests/`) :

```
user/refresh        → 0 fichier
user/account        → 0 fichier
user/device-tokens  → 0 fichier
```

---

## 2. Les 8 cas de la matrice de connexion — état réel

Source : `docs/design/PUBLIC_ACCOUNT_CONNECTION_MATRIX.md`.

| # | Cas | Test | État |
|---|---|---|---|
| 1 | E-mail seul | `RegisterLoginFlowTest` | ✅ couvert |
| 2 | Téléphone seul | `PublicPhoneOtpTest` (14) | ✅ couvert **et exécuté** — transport SMS réel = lot L1 |
| 3 | Compte agence de voyage (pont téléphone vérifié) | `PublicIdentityBridgeServiceTest` (10) | ✅ couvert **et exécuté** |
| 4 | Personne cliente de plusieurs métiers | `PublicActivityTest` (14) | ✅ couvert **et exécuté** |
| 5 | Cliente **et** employée | `PublicPhoneOtpTest::test_user_api_token_does_not_open_employee_surface` | ✅ couvert **et exécuté** |
| 6 | Numéro changé / réattribué | `PublicIdentityBridgeServiceTest::test_changing_phone_never_transfers_user_id` | ✅ couvert **et exécuté** |
| 7 | SMS indisponible | `LoginCodeSmsChannelTest` (16) | ✅ couvert **et exécuté** |
| 8 | Appareil perdu → révocation | `PublicLogoutAllTest` (5) | ✅ couvert **et exécuté** |

**Aucun des 8 cas n'est plus « non couvert ».** Les cas 3 et 6, que le document déclarait « non implémentés » à sa date du 2 novembre, ont été livrés par M2 (#9104).

---

## 3. Profils attendus — état réel

| Profil | Ce qu'il doit pouvoir faire | État | Preuve |
|---|---|---|---|
| **Visiteur anonyme** | chercher, voir une fiche, contacter sans compte | ✅ couvert | `PublicParcoursEndToEndTest` (19), `PublicDiscoverableProviderContractTest` (7) |
| **Client e-mail** | compte, conversations, activités, favoris | ✅ couvert | `RegisterLoginFlowTest`, `PublicActivityTest` |
| **Client téléphone (OTP)** | compte sans e-mail | ✅ couvert | `PublicPhoneOtpTest` (14) |
| **Client agence de voyage** | retrouver le **même** compte `users` | ✅ couvert | `PublicIdentityBridgeServiceTest` (10) |
| **Client multi-commerces** | N conversations, N activités, aucune permission partagée | ✅ couvert | `PublicActivityTest` (14) |
| **Personne cliente ET employée** | deux comptes séparés, jetons non interchangeables | ✅ couvert | `PublicPhoneOtpTest` (isolation de guard) |
| **Client sur appareil perdu** | révoquer tous ses jetons | ✅ couvert | `PublicLogoutAllTest` (5) |
| **Client qui supprime son compte** | suppression différée 30 j, réversible, ré-authentifiée | ✅ **comblé par ce lot** | `PublicAccountLifecycleTest` (5) |
| **Client qui change d'appareil** | réaffectation du jeton push, pas de fuite vers l'ancien | ✅ **comblé par ce lot** | `PublicDeviceTokenTest` (10) |
| **Client qui consulte ses activités (PWA)** | onglet Activités | ⚠️ **partiel** | API ✅ (`/user/activities`), **route PWA `/activites` absente** — §4 |
| **Client qui gère son profil (PWA)** | onglet Profil | ⚠️ **partiel** | API ✅ (`/user/me`, `/user/account`), **route PWA `/profil` absente** — §4 |
| **Client qui exporte ses données** | export RGPD | ❌ **absent** | aucune route, aucun test — §4 |

---

## 4. Manques de fond trouvés — **hors périmètre de ce lot**

Ces trois points sont **documentés et signalés**, pas construits ici : ce lot est un audit + comblement de tests, et la règle « une PR par lot » interdit d'y ajouter des écrans ou un schéma.

### 4.1 Routes PWA `/activites` et `/profil` absentes → **liens de navigation morts**

`front/web/src/modules/public-portal/components/PublicBottomNav.tsx:62-67` déclare :

```ts
const TAB_HREF = {
  home: '/', messages: '/conversations',
  activities: '/activites', profile: '/profil',
};
```

Or `front/web/src/app/activites/` et `front/web/src/app/profil/` **n'existent pas** (vérifié : `test -d` → MISSING). Un client qui touche « Activités » ou « Profil » dans la barre basse tombe sur un 404.

C'est exactement le « bouton mort » que la règle du couloir interdit. **Correctif attendu** : deux routes PWA consommant les API déjà livrées (`/user/activities`, `/user/favorites`, `/user/me`, `/user/account`). **Non fait ici** — mérite son propre lot.

### 4.2 Table `public_profiles` absente

`docs/design/PUBLIC_PROFILE_DATA_MODEL.md` §3 la décrit et §11 la marque « ⬜ à créer ». Vérifié : **aucune migration, aucun modèle** (`grep -rln "public_profiles" database/migrations/` → vide).

Conséquence : `preferred_locale`, `approx_city`, `approx_country`, `notifications_opt_in` et `consent_at` n'ont **aucun lieu de stockage**. L'onglet Profil Flutter écrit la langue **localement** (`appPreferencesProvider`), ce qui fonctionne sur un appareil mais ne suit pas le compte.

**Non fait ici** : c'est un changement de schéma, hors périmètre d'un lot d'audit.

### 4.3 Export de données RGPD absent

Le contrat §2.6 impose la suppression ; l'export n'a **ni route ni test**. L'onglet Profil Flutter l'affiche honnêtement comme « bientôt disponible » (`profileComingSoon`) — donc **aucune fausse promesse à l'écran**, mais l'exigence RGPD reste non servie.

---

## 5. Preuves d'exécution de ce lot

Environnement : PHP 8.4, PostgreSQL 15, base de test migrée, `CI=true` (sans quoi `TestCase::isRunningInsideDocker()` force `DB_HOST=pgsql` et tout échoue sur une erreur de connexion sans rapport).

```
$ CI=true php artisan test \
    tests/Feature/LeopardoPublic/PublicAccountLifecycleTest.php \
    tests/Feature/LeopardoPublic/PublicDeviceTokenTest.php

  Tests:    19 passed (65 assertions)
  Duration: 2.71s
```

Détail des 19 tests :

**`PublicAccountLifecycleTest` (9)** — `refresh` : 401 sans jeton ; nouveau jeton rendu et **ancien mort** (vérifié en rappelant `/user/me` avec l'ancien → 401, avec le nouveau → 200) ; la rotation **ne multiplie pas** les jetons. `DELETE /account` : 401 sans jeton ; mauvais mot de passe → 401 **et rien ne bouge** (ni marquage, ni purge, ni révocation) ; mot de confirmation absent → 422 ; suppression **différée de 30 jours** exactement (comparée à `deletion_requested_at + 30 j`, pas à `now()` — `diffInDays` tronque et donnerait 29) ; **tous** les appareils révoqués ; **le compte voisin intact**.

**`PublicDeviceTokenTest` (10)** — 401 sans jeton (enregistrement et suppression) ; jeton rattaché au compte authentifié ; **`user_id` du corps ignoré** (tentative d'usurpation) ; même jeton deux fois → **mise à jour, pas doublon** ; **appareil revendu → réaffecté** au nouveau propriétaire ; plateforme inconnue → 422 ; désenregistrement de son propre appareil ; **impossible de désenregistrer celui d'autrui** (204 idempotent mais la ligne survit) ; jeton inconnu → 204.

**Un échec rencontré et corrigé, pas contourné** : la première version comparait `now()->diffInDays($scheduled)` et obtenait **29** au lieu de 30 — quelques millisecondes séparent l'appel du contrôleur de celui du test, et `diffInDays` tronque. Le test a été corrigé pour comparer à `deletion_requested_at + 30 jours`, ce qui est exact quelle que soit l'heure. **L'assertion n'a pas été affaiblie** (elle est plus stricte : elle vérifie la date exacte, pas un arrondi).

---

## 6. Ce que ce lot ne prétend pas

- **Aucun envoi FCM/APNs testé** : `PublicDeviceTokenController` n'envoie rien (écrit dans son en-tête). L'envoi réel dépend d'un compte de développeur — **porte (c), aucune dépense engagée**.
- **Aucun e-mail de confirmation de suppression testé** : le marquage et la planification le sont ; l'envoi et l'annulation à J+30 relèvent du lot de purge (L1/L4).
- **Aucun seuil de qualité mesuré** : pas d'appareil Android, pas de bridage réseau, pas de Flutter dans le sandbox. Les seuils de `PUBLIC_APP_BUDGETS.md` restent **en attente**, jamais déclarés tenus.
- **Aucun écran créé** : `/activites`, `/profil` et `public_profiles` sont **signalés**, pas construits.

---

## 7. Portes respectées

| Porte | Règle | État |
|---|---|---|
| **(a)** | Une PR par lot, ouverte, **jamais mergée** | ✅ PR ouverte, non mergée |
| **(b)** | M2 : attend L1 + contrat L4 | ✅ M2 non touchée |
| **(c)** | Aucune dépense nouvelle | ✅ aucune dépense |
| **Architecture n°1** | Canal, pas verticale ; aucun BC ajouté ; aucune duplication | ✅ aucun BC, aucune table, aucune route ajoutés |
