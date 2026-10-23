# 10 — Rapport final « Leopardo Public »

**Base :** `main` @ `ebdacdbd2` · **Date :** 2026-10-22 (voir §11 pour la mise à jour de vérification)
**PR de cet incrément :** [#8818](https://github.com/kitokoh/leopardo-hr/pull/8818) — **fusionnée** dans `main` en `ebdacdbd2` · **Branche :** `fix/8762-geolocation-permissions-policy` (supprimée après fusion)
**PR fondatrice (fusionnée) :** [#8763](https://github.com/kitokoh/leopardo-hr/pull/8763) — `feat/8758-leopardo-public-gateway`, mergée en `0c93465b4`
**Issues :** #8758 (fermée), #8760 (fermée), #8759 / #8761 / #8762 (ouvertes)

---

## 0. Ce que ce run a réellement changé (résumé exécutif)

Le parcours public était déjà largement livré par d'autres agents entre-temps
(`main` avait avancé de 45 commits, la branche `feat/8758-…` avait été mergée puis
supprimée en amont). L'audit de ce run a donc porté sur **ce qui manquait encore**,
et a trouvé **un vrai bug produit bloquant**, invisible jusqu'ici faute de test :

> `next.config.ts` émettait `Permissions-Policy: geolocation=(), microphone=(), camera=()`.
> `geolocation=()` **désactive `navigator.geolocation` au niveau du document** sur tout le
> site. Chromium refusait donc la demande **avant** l'autorisation utilisateur
> (`Permissions policy violation`, code 1 — mesuré dans les logs console Playwright).
> Conséquence : **le bouton « Autour de moi » de `/decouvrir` ne pouvait jamais
> fonctionner en production**, et le repli sur la saisie manuelle n'était jamais atteint.

Correctif livré en PR #8818 : `geolocation=(self)` (notre origine uniquement ; micro et
caméra restent `()`), plus **5 tests E2E** qui n'existaient pas — le seul trou de
couverture du parcours public.

### Statuts, sans ambiguïté

| Statut | Éléments |
|---|---|
| **Implémenté** | Correctif `Permissions-Policy`, spec E2E géolocalisation (5 scénarios) |
| **Testé** | 5/5 E2E géo · 13/13 E2E non-régression · 21/21 Jest `/decouvrir` · 19/19 parcours E2E backend · 60/60 contact+discovery+conversation · 18/18 profils/annuaires · 7/7 commande+paiement restaurant · `tsc` 0 erreur · ESLint 0 warning · 4 gardes OK |
| **Intégré** | Poussé sur `fix/8762-geolocation-permissions-policy`, PR #8818 ouverte, CI verte sur l'essentiel |
| **Déployé** | ❌ Non |
| **Disponible pour les utilisateurs** | ❌ Non — PR #8818 non fusionnée |

---

## 1. État initial constaté (à l'ouverture de ce run)

Le dépôt est un monolithe modulaire Laravel 12 / PHP `^8.4.1` (DDD 5 couches, **37 modules**,
registre machine de **37 BC** — le brief en annonçait 36 : BC-22 `planned`, BC-38 ajouté par
#8696), avec 6 frontends Next.js/Vue, 9 apps Flutter, un socle multi-tenant
(`public` + `shared_tenants`, `BelongsToCompany`, `TenantManager`, `PublicTenantResolver`),
Sanctum + RBAC + policies, un BC paiement (BC-38) avec webhooks idempotents, un module
Notification portant le chat, et un BC Geo (`GeoServiceContract`, PostGIS/Haversine).

**Synchronisation :** `git fetch --all --prune` a révélé que
`origin/feat/8758-leopardo-public-gateway` **avait été mergée puis supprimée**, et que
`main` avait avancé de `c358f109a` à `f8a43a423`. Les 8 commits de la mission fondatrice
sont tous ancêtres de `main` (vérifié par `git merge-base --is-ancestor` sur chacun).
Un autre agent avait livré #8760 en `ab0ca5d2a` (service de conversation public, inbox pro,
2 migrations, `PublicConversationTest`, pages `/conversations` et `/demandes-publiques`).

**Travail local non commité trouvé** : un lot redondant (`PublicContactInboxController`,
`ReplyToPublicContactRequestAction`, `PublicContactJourneyTest`). Il dupliquait
l'architecture réelle de `ab0ca5d2a` (le chat passe par `Notification`, pas par
`Platform`) et **n'a pas été poussé**. Archivé hors dépôt
(`/workspace/archive/run3-uncommitted-*`) puis retiré de l'arbre. **Aucun travail
d'agent écrasé.**

## 2. Composants existants réutilisés (aucun reconstruit)

| Composant | Fichier | Usage |
|---|---|---|
| Contrat de découverte | `api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` | Consommé tel quel, `supportedActions()` inclus |
| Agrégateur | `api/app/Shared/Discovery/PublicDiscoveryService.php` | `GET /api/v1/public/discover` — dédup, distances, tri, pagination, `meta.actions` |
| Contrôleur | `api/app/Modules/Platform/.../PublicDiscoveryController.php` | Route isolée `throttle:shop-public`, sans auth ni middleware tenant |
| Providers | Restaurant / Hospitality / Showcase / Retail / VTC / Trade | Fail-closed (`is_public`, statut société, feature verticale) |
| Chat public | `Notification\Application\Services\PublicConversationService` | `POST/GET /api/v1/public/conversations` + inbox pro `/conversations/public` |
| Contact public | `Platform\...\PublicContactRequestController` | `POST /api/v1/public/contact-requests` |
| Notifications | `CommunicationService` | Préférences, heures calmes, quotas |
| Fiches publiques | `/restaurants/{slug}`, `/boutiques/{slug}`, `/operateurs/{slug}`, `/vitrine/{slug}`, `/stay/{slug}` | Cibles des liens `item.url` |

## 3. Fonctionnalités effectivement implémentées dans **ce** run

1. **Correctif `Permissions-Policy`** (`front/web/next.config.ts`) — `geolocation=(self)`.
   Le seul en-tête modifié ; microphone/caméra restent totalement bloqués.
2. **Spec E2E `e2e/leopardo-public-geolocation.spec.ts`** (nouveau, 5 scénarios) :
   autorisation accordée (`near=36.75380,3.05880`, `radius_km` borné 0–50, position
   **jamais** dans l'URL partageable), effacement, refus (message explicite, **aucun**
   `near` émis), repli manuel ville/zone après refus, navigateur sans API.

## 4. Fichiers / BC / interfaces modifiés

**Aucun BC créé. Aucun fichier backend modifié dans ce run.**

| Fichier | Nature |
|---|---|
| `front/web/next.config.ts` | modifié (+9 / −1) |
| `front/web/e2e/leopardo-public-geolocation.spec.ts` | nouveau (203 lignes) |

## 5. Migrations et configuration

**Aucune migration.** Un en-tête HTTP modifié. Aucune variable d'environnement, aucun secret.

## 6. Tests réellement exécutés (sorties réelles)

| Commande | Résultat mesuré |
|---|---|
| `playwright test e2e/leopardo-public-geolocation.spec.ts` | **5 passed** (14.4 s) |
| `playwright test e2e/leopardo-public-parcours.spec.ts e2e/security-headers.spec.ts` | **13 passed** (35.5 s) |
| `jest src/app/decouvrir` | **21 passed**, 3 suites (16.0 s) |
| `tsc --noEmit` | **exit 0**, 0 erreur |
| `eslint src --max-warnings 0` | **exit 0**, 0 warning |
| `eslint e2e --max-warnings 0` | **exit 0**, 0 warning |
| `check:mojibake` | `OK — no double-encoded UTF-8 found` |
| `check:assets` | `5 asset(s) OK` |
| `check:orphans` | `27 pages (landing) : toutes sont liées en interne` |
| `check:french-copy` | `OK — aucune forme interdite` |
| `php artisan test --filter=PublicParcoursEndToEndTest` | **19 passed** (90 assertions) |
| `php artisan test --filter='PublicContactRequestTest\|PublicDiscoveryTest\|PublicConversationTest'` | **60 passed** (212 assertions) |
| `php artisan test --filter='RestaurantBranchPublicProfileTest\|RestaurantPublicDirectoryTest\|HospitalityPublicStaySurfaceTest\|RetailMarketPublicTest'` | **18 passed** (190 assertions) |
| `php artisan test --filter=RestaurantPublicOrderTest` | **7 passed** (26 assertions) |

**Vérification du correctif sur serveur réel :**
`HTTP/1.1 200` sur `/decouvrir` avec
`Permissions-Policy: geolocation=(self), microphone=(), camera=()`.

### Couverture des 10 scénarios d'acceptation obligatoires

| # | Scénario | Couvert par | Verdict |
|---|---|---|---|
| 1 | Découverte dans une zone, profils publiables/actifs seulement | `PublicParcoursEndToEndTest` step 2 + `PublicDiscoveryTest` | ✅ testé |
| 2 | Fiche publique sans données internes | step 2b (liste blanche du DTO) + E2E `le DTO public ne divulgue aucun champ interne` | ✅ testé |
| 3 | Contact : le pro reçoit le message | steps 4→8 | ✅ testé |
| 4 | Réponse du pro + notification | steps 4→8 + `PublicConversationTest` | ✅ testé |
| 5 | Isolation multi-tenant | step 9, 9b, 9e (404 uniforme, jamais 403) | ✅ testé |
| 6 | Rôles (client ≠ manager/admin) | step 9c, 9d, 9e | ✅ testé |
| 7 | Profil désactivé absent des résultats | step 10, 10b, 10c | ✅ testé |
| 8 | Service métier / état indisponible explicite | `RestaurantPublicOrderTest` + `meta.actions` fail-closed (Jest) | ✅ testé |
| 9 | Paiement confirmé côté backend | `RestaurantPublicOrderTest::test_public_payment_initiates_mobile_money` | ✅ testé |
| 10 | Non-régression employé/manager/admin | step 9c (employé) + 18/18 annuaires/profils + CI PR (ESLint+TS, Web Build/Lint, PHPStan, Hygiene) | ✅ testé |

## 7. Ce qui n'est PAS terminé, et pourquoi

1. **`next build` de production non exécuté ici.** Le sandbox est plafonné à
   **2 Go** (`/sys/fs/cgroup/memory.max`), avec 51 OOM kills comptés : `next build`,
   `tsc` à froid et `next dev` à froid y meurent en `exit 137`. Les E2E ont tourné contre
   un serveur de dev précompilé (route chaude, 7,9 s) ; le build prod est laissé à la CI,
   qui l'exécute (`Web Marketing Build` = success sur la PR).
2. **Fusion en `main` non faite.** L'autorisation de merge explicite n'a pas été donnée
   par l'utilisateur (règle de la mission), et la décision revient au propriétaire.
   La branche est conservée, la PR #8818 est ouverte et verte sur l'essentiel.
3. **#8759 et #8761 restent ouvertes** : leur périmètre est couvert par le code déjà
   mergé (`item.url`, `meta.actions`, CTA conditionnels), mais elles n'ont pas été
   refermées par ce run — décision de clôture à prendre, avec la preuve de couverture
   ci-dessus.
4. **Deux checks CI en cours** au moment de la rédaction (`lighthouse`,
   `Smoke build leopardo_employee`) — aucun lien avec le périmètre (front web `next.config.ts`
   + un spec E2E).

## 8. Risques restants

- **Faible** : `geolocation=(self)` ouvre la géolocalisation à notre seule origine.
  `microphone`/`camera` restent `()` ; aucun iframe tiers ne peut demander la position.
- **UX** : les visiteurs verront désormais la demande d'autorisation native **après un
  clic** sur « Autour de moi » (aucune collecte au chargement — comportement déjà en
  place, désormais **testé**).
- **Dette CI préexistante de `main`** : `Déploiement aligné (dev)` et
  `Déploiement aligné (prod)` échouent (dérive de déploiement, sans lien avec ce lot).
  Non masqué, non corrigé ici.

## 9. Liens

- **PR (à fusionner) :** https://github.com/kitokoh/leopardo-hr/pull/8818
- **Branche :** `fix/8762-geolocation-permissions-policy`
- **PR fondatrice (fusionnée) :** https://github.com/kitokoh/leopardo-hr/pull/8763 → `0c93465b4`
- **Issues :** #8758, #8759, #8760, #8761, #8762

## 10. Prochaines étapes recommandées

1. Fusionner #8818 (revue + merge autorisé) — c'est le correctif qui rend « Autour de moi »
   réellement fonctionnel.
2. Refermer #8759 / #8761 / #8762 avec la matrice de couverture du §6.
3. Traiter la dette CI `Déploiement aligné (dev)` / `(prod)` dans un lot dédié.
4. Étendre l'E2E géolocalisation aux autres surfaces publiques (`/restaurants`,
   `/boutiques`) qui exposent le même bouton.

---

## 11. Mise à jour de vérification — `main` @ `ebdacdbd2` (2026-10-22)

> Section **ajoutée** par le run de vérification. Les §0–§10 ci-dessus sont conservés
> tels quels (travail d'autres agents) ; ce qui suit les **corrige ou les complète**.

### 11.1 Synchronisation

`git fetch --all --prune` + `git pull --ff-only origin main` : `main` **à jour** sur
`ebdacdbd2`. La branche `feat/8758-leopardo-public-gateway` n'existe **plus** ni en local
ni en distant (supprimée par GitHub à la fusion de #8763, puis localement).
**Aucun travail d'agent écrasé** : les 23 commits arrivés depuis `0c93465b4` sont tous
présents et intacts.

### 11.2 `PublicContactJourneyTest` : n'existe pas

Le fichier `api/tests/Feature/Platform/PublicContactJourneyTest.php` **n'existe nulle part
dans le dépôt** (`find api/tests -name '*Journey*'`). Ce n'est **pas** une régression : il
n'a jamais été poussé (le lot local du run précédent avait été archivé hors dépôt, cf. §1).
Sa couverture est assurée par deux suites réelles, toutes deux vertes :
`PublicContactRequestTest` et `PublicDiscoveryTest` (qui inclut `VtcPublicDiscoveryTest`).

### 11.3 Tests réellement exécutés dans ce run (sorties mesurées)

| Commande | Résultat réel |
|---|---|
| `php artisan test --filter=PublicContactRequestTest` | **10 passed** (35 assertions) |
| `php artisan test --filter=PublicDiscoveryTest` | **32 passed** (143 assertions) — inclut `VtcPublicDiscoveryTest` |
| `php artisan test --filter='PublicParcoursEndToEndTest\|PublicConversationTest'` | **37 passed** (124 assertions) |
| `npx jest src/app/decouvrir` | **21 passed**, 3 suites |
| `npm run typecheck` (`tsc --noEmit`) | exit 0, 0 erreur |
| `npm run lint` (`eslint src --max-warnings 0`) | exit 0, 0 warning |
| `npm run lint:e2e` (`eslint e2e --max-warnings 0`) | exit 0, 0 warning |
| `npm run check:mojibake` | `OK — no double-encoded UTF-8 found` |
| `npm run check:assets` | `5 asset(s) OK` |
| `npm run check:orphans` | `27 pages (landing) : toutes sont liées en interne` |
| `npm run check:french-copy` | `OK — aucune forme interdite` |
| `node shared/i18n/validators/validate.js` | `I18N_VALIDATION_OK (4 locales)` |
| `bash dev-hub/tools/check-i18n-catalog-parity.sh` | `parité i18n OK (backend api/lang, shared/i18n, ARB mobile — 4 langues)` |
| `python3 dev-hub/tools/check-i18n-locale-parity.py` | fr/en/ar/tr : **6961 clés**, 0 manquante, 0 surnuméraire |
| `node dev-hub/tools/check-i18n-diff.js main HEAD` | `nothing to check` (aucune surface à risque modifiée) |

**Environnement local** : PostgreSQL 15 démarré, et `/etc/hosts` complété par
`127.0.0.1 pgsql` — `tests/TestCase.php:106` bascule l'hôte sur le nom de service Docker
`pgsql` dès qu'on tourne dans un conteneur hors CI. **Aucune modification du dépôt** pour ça.

### 11.4 E2E navigateur : présents, non réécrits

Les deux specs existent déjà sur `main` et couvrent exactement le périmètre demandé :

- `front/web/e2e/leopardo-public-geolocation.spec.ts` (203 lignes, **5 scénarios**) :
  autorisation accordée (`near=36.75380,3.05880`, `radius_km` borné 0–50, position jamais
  dans l'URL partageable), effacement de la position, **refus** (message explicite, aucun
  `near` émis), **repli manuel ville/zone après refus**, navigateur sans API géoloc.
- `front/web/e2e/leopardo-public-parcours.spec.ts` (263 lignes) : découverte SSR, type
  inconnu → 422, aucun faux lien de fiche, DTO sans champ interne, état vide, fiches
  Retail/VTC fail-closed, conversation (401 → connexion, fil d'un autre client → 404).

Aucun test E2E n'a été réécrit : ils étaient substantiellement corrects.

### 11.5 Classification CI de `main` @ `ebdacdbd2`

**79 check-runs, 11 non-verts** — et **aucun n'est causé par #8763 ni #8818** :

| Check | Conclusion | Classement |
|---|---|---|
| `Déploiement aligné (dev)` ×2 | failure | **Dette préexistante** — déjà en échec sur `f8a43a423`, avant #8818 |
| `Déploiement aligné (prod)` ×2 | failure | **Dette préexistante** — idem |
| `Sonde prod + watchdog` ×3 | failure | **Infra prod** (watchdog de disponibilité), hors périmètre |
| `Backend Coverage (PHP 8.4 + PostgreSQL 16)` | cancelled | **Annulation** (run concurrent), pas un échec |
| `Rapport d'état des checks (#7480)` | queued | **En vol** au moment du relevé |
| `Rattraper la prod Render si en retard sur main (#8092)` | queued | **En vol** |
| `Re-dispatch deploy-main si dev en retard` | queued | **En vol** |

**Améliorations constatées** (dette résorbée par d'autres agents entre-temps) :
`Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` était en **failure** sur `f8a43a423` **et**
sur `0c93465b4` (fusion de #8763) — il est **vert** sur `ebdacdbd2` (corrigé par #8796).
Les 8 `Build Debug leopardo_*` en échec sur `0c93465b4` sont également **verts** sur
`ebdacdbd2` (corrigés par #8776 / #8790).

**Verdict :** `main` est **vert sur tout ce qui touche le parcours public** (Frontend
ESLint+TS, Web Build/Lint/E2E, PHPStan Strict level 8, PHPStan Modules Architecture,
Hygiene Guards, TruffleHog, Lighthouse, Web Marketing Funnel E2E). Les échecs restants
sont de l'infra de déploiement/sonde prod, **sans lien** avec ce périmètre.

### 11.6 Statuts après ce run

| Statut | Éléments |
|---|---|
| **Implémenté** | Rien de nouveau dans ce run — le parcours public est déjà livré sur `main` (#8763, #8760, #8802, #8808, #8818) |
| **Testé** | 79 tests backend + 21 Jest + 7 gardes + 4 contrôles i18n, **tous verts** (§11.3) ; E2E géoloc/parcours présents (§11.4) |
| **Intégré** | #8763 et #8818 **fusionnées dans `main`** ; branche de travail supprimée |
| **Déployé** | ❌ Non — aucun déploiement déclenché par ce run |
| **Disponible pour les utilisateurs** | ⚠️ **Code sur `main`**, mais la mise à disposition dépend du pipeline de déploiement, dont les checks `Déploiement aligné (dev)/(prod)` sont **en échec préexistant** |

### 11.7 Ce qui reste ouvert

1. **#8759 et #8761 restent ouvertes** : leur périmètre est couvert par le code déjà
   fusionné (`item.url`, `meta.actions`, CTA conditionnels fail-closed), mais la
   **décision de clôture** revient au propriétaire — ce run ne ferme pas d'issue.
2. **Dette CI de déploiement** (`Déploiement aligné (dev)/(prod)`, `Sonde prod + watchdog`) :
   préexistante, non masquée, non corrigée ici — elle relève d'un lot d'infra dédié.
3. **Aucune PR de correctif ouverte** : aucun échec CI n'étant imputable à #8763/#8818,
   il n'y a rien à corriger.


---

> **Suite du rapport** — la correction de #8761 (contrat `supportedActionUrls`, `meta.action_urls`, CTA recâblés) est documentée dans [§12](12_leopardo_public_section12_8761.md).
