# 10 — Rapport final « Leopardo Public »

**Branche :** `feat/8758-leopardo-public-gateway` · **PR :** [#8763](https://github.com/kitokoh/leopardo-hr/pull/8763)
**Base :** `main` @ `c358f109a` · **Date :** 2026-10-20
**Issues :** #8758, #8759, #8760, #8761, #8762

---

## 1. État initial constaté

Le dépôt est un monolithe modulaire Laravel 12 / PHP `^8.4.1` (DDD 5 couches, **37 modules**,
registre machine de **37 BC**), avec 6 frontends Next.js/Vue, 9 apps Flutter, un socle
multi-tenant (`public` + `shared_tenants`, `BelongsToCompany`, `TenantManager`,
`PublicTenantResolver`), Sanctum + RBAC + policies, un BC paiement (BC-38) avec webhooks
idempotents, un module Notification portant le chat (`ConversationThread`/`ConversationMessage`),
et un BC Geo (`GeoServiceContract`, PostGIS/Haversine).

**Aucune branche ni PR existante** ne portait sur la passerelle publique (6 PR ouvertes vérifiées :
#8732, #8721, #8668, #8642, #8573, #8572) — aucun travail d'agent antérieur à préserver sur ce
périmètre.

## 2. Composants existants réutilisés

| Composant | Fichier | Usage |
|---|---|---|
| Contrat de découverte | `api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` | Consommé tel quel (inversion de dépendance, garde d'isolation #5584) |
| Agrégateur | `api/app/Shared/Discovery/PublicDiscoveryService.php` | `GET /api/v1/public/discover` — dédup, distances, tri, pagination |
| Contrôleur | `api/app/Modules/Platform/.../PublicDiscoveryController.php` | Route isolée `throttle:shop-public`, sans auth ni middleware tenant |
| Providers | Restaurant / Hospitality / Showcase | Fail-closed (`is_public`, statut société, feature verticale) |
| Fiches publiques | `/vitrine/{slug}`, `/public/restaurants/{slug}`, `/public/hospitality/properties/{slug}` | Cibles des liens de sortie (`item.url`) |
| i18n | `shared/i18n` + `sync-web`/`sync-mobile`/`sync-backend` | Source unique, propagation automatique |
| Design system | `front/web/src/app/globals.css`, tokens | Cohérence LEOPARDO |

## 3. Fonctionnalités effectivement implémentées

1. **Client SSR du contrat public** — `front/web/src/lib/public-discovery-api.ts` : DTO strict,
   bornes `per_page` (≤ 50) et `radius_km` (0,1–50) miroir du service, `type` inconnu **jamais
   transmis** (l'API répond 422), erreur réseau → `null` (jamais de throw en SSR).
2. **Page SSR indexable `/decouvrir`** — `front/web/src/app/decouvrir/page.tsx` : recherche
   métier/service/nom, ville, catégorie, pagination, mobile-first, `generateMetadata` + canonical.
3. **Explorateur client** — `discovery-explorer.tsx` : catégories dérivées de `meta.types`,
   géolocalisation **sur action explicite** avec **repli saisie manuelle ville/zone**, états
   chargement / vide / erreur / zone non couverte, pagination SSR crawlable hors proximité.
4. **Résultats agrégés multi-verticales** — chaque carte pointe vers la fiche publique verticale
   fournie par l'API ; CTA réservation **uniquement** pour les verticales qui la prennent en
   charge, sinon état explicite « indisponible ».
5. **i18n** — 31 clés `discovery.*` dans `shared/i18n/locales/{fr,en,ar,tr}.json`, propagées par
   les trois syncs ; lien footer ajouté (garde anti-orphelin #8075).

## 4. Fichiers / BC / interfaces modifiés

**Aucun BC créé. Aucun fichier backend modifié.**

| Fichier | Nature |
|---|---|
| `front/web/src/lib/public-discovery-api.ts` | nouveau |
| `front/web/src/app/decouvrir/page.tsx` | nouveau |
| `front/web/src/app/decouvrir/discovery-explorer.tsx` | nouveau |
| `front/web/src/app/decouvrir/__tests__/*.test.{ts,tsx}` | nouveaux (2 fichiers) |
| `front/web/src/modules/vitrine/components/Footer.tsx` | +1 lien |
| `front/web/src/modules/vitrine/lib/vitrine-locale.ts` | +1 libellé ×4 langues |
| `shared/i18n/locales/{fr,en,ar,tr}.json` | +31 clés |
| `front/web/src/lib/i18n/locales/*`, `front/admin-dashboard/src/i18n/locales/*`, `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb` | **générés** par les syncs |
| `CHANGELOG.md`, `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` | gouvernance |
| `docs/mission-business-os/09_leopardo_public_audit.md`, `10_..._rapport_final.md` | livrables |

## 5. Migrations et changements de configuration

**Aucune migration. Aucun changement de configuration. Aucun secret.**

## 6. Tests exécutés et résultats réels

| Garde | Résultat |
|---|---|
| `npx tsc --noEmit` | ✅ 0 erreur |
| `npm run lint` (`eslint src --max-warnings 0`) | ✅ 0 warning |
| `npx jest src/app/decouvrir` | ✅ **13/13** |
| `npx jest src/modules/vitrine src/app/restaurants src/app/__tests__` | ✅ **713/713** (66 suites) |
| `check:mojibake` / `check:assets` / `check:orphans` / `check:french-copy` | ✅ OK |
| `shared/i18n/validators/validate.js` | ✅ `I18N_VALIDATION_OK (4 locales)` |
| `check-i18n-catalog-parity.sh` | ✅ parité fr/en/ar/tr |
| `check-i18n-diff.js main HEAD` | ✅ aucune chaîne hardcodée nouvelle |
| `next build` (compilation) | ✅ compilé, `/decouvrir` dans `app-path-routes-manifest.json` |

**CI GitHub** — verts : `Frontend — ESLint + TypeScript`, `Web Build`, `Web Lint`, `Web E2E
Playwright`, `Web Marketing Lint/Build`, `I18N — No new hardcoded strings`, `i18n Comptabilité ×4`,
`validate-and-sync`, `Governance Gates`, `Route → OpenAPI coverage guard`, `Hygiene Guards`,
`TruffleHog Secret Scan`, `Module Structure Validator`, `PHPStan — Strict (level 8)`, `actionlint`,
`Dependency Review`, `check-tokens`, `Lint OpenAPI contract`, `Merge Health Guard`, `Ratio fix/feat`,
`Nommage produit`, `Mobile apps split guard`, `edge-guards`, `Quota merges quotidiens`.

**Échecs restants — tous préexistants sur `main`** (comparaison avec `main` @ `c358f109a` et les
runs `main` de `mobile-apps-ci.yml` / `desktop-ci.yml` / `tests.yml`) : `Analyze leopardo_hr`,
`Analyze leopardo_employee`, `Analyze leopardo_manager`, `Analyze leopardo_platform_admin`,
`Desktop verify leopardo_*` (win/mac), `Tests module Accounting`, `Backend (PHP 8.4 + PG 16 + Redis 7)`,
`Backend Coverage`. **Aucun n'est causé par cette PR.**

## 7. Éléments non terminés et raisons

| Élément | Raison |
|---|---|
| Fusion dans `main` | **Non effectuée** : l'autorisation de merge n'a pas été donnée (règle explicite de la mission) et `main` est **déjà rouge** (9 échecs préexistants) — « main propre et vert » n'est pas atteignable sans corriger la dette de `main` |
| Suppression de la branche | Conséquence de la non-fusion |
| Phase « Collecting page data » du build | Tuée par la limite mémoire du sandbox (cgroup 2 Gio, 47 workers) — contrainte d'environnement |
| CTA contact/messagerie (issue #8760) | Non implémenté : nécessite de vérifier les capacités backend du chat côté client public (aucun endpoint public de conversation n'existe aujourd'hui) — à traiter dans un incrément dédié |
| CTA service/réservation (issue #8761) | Partiellement : l'affichage conditionnel est livré ; le branchement effectif aux flux Catalog/Restaurant reste à faire |
| Tests E2E navigateur (géoloc réelle, refus d'autorisation) | Non exécutés localement (pas de navigateur piloté dans cet environnement) |

## 8. Risques restants

- **Faible** : aucun fichier backend touché, nouvelles routes frontend isolées, aucune donnée
  interne exposée (DTO public déjà strict).
- Le CTA réservation dépend de la disponibilité de `item.url` par verticale ; en son absence
  l'état « indisponible » est affiché (comportement voulu, pas un bug).
- Les jobs mobiles/desktop se déclenchent parce que la propagation i18n touche
  `front/mobile_apps/**` ; leur échec est identique sur `main` sans mes modifications.

## 9. Lien de la branche / de la PR

- Branche : `feat/8758-leopardo-public-gateway`
- PR : https://github.com/kitokoh/leopardo-hr/pull/8763
- Issues : #8758, #8759, #8760, #8761, #8762

## 10. Prochaines étapes recommandées

1. **Autoriser la fusion** de #8763 (ou demander les corrections souhaitées).
2. Traiter la dette CI de `main` (jobs mobiles/desktop/Accounting) — elle bloque tout « main vert ».
3. Incrément suivant : CTA contact/messagerie (#8760) après vérification des capacités backend.
4. Brancher le CTA réservation aux flux métier réels (#8761) pour la première verticale.
5. Ajouter les tests E2E navigateur (géolocalisation, refus d'autorisation, zone non couverte).

---

## Distinction des statuts

| Statut | Éléments |
|---|---|
| **Implémenté** | Client SSR, page `/decouvrir`, explorateur, résultats agrégés, i18n, lien footer |
| **Testé** | 13 tests unitaires + 713 tests de non-régression, typecheck, lint, 4 gardes, i18n ×3, build |
| **Intégré** | Consomme le contrat public existant `GET /api/v1/public/discover` (déjà en production sur `main`) |
| **Déployé** | ❌ Non — aucun déploiement effectué (interdit sans autorisation explicite) |
| **Disponible pour les utilisateurs** | ❌ Non — la PR n'est pas fusionnée, donc la page n'est pas servie en production |
