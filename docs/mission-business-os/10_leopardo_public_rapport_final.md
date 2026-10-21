# 10 — Rapport final « Leopardo Public »

**Base :** `main` @ `f8a43a423` · **Date :** 2026-10-21
**PR de cet incrément :** [#8818](https://github.com/kitokoh/leopardo-hr/pull/8818) · **Branche :** `fix/8762-geolocation-permissions-policy`
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
