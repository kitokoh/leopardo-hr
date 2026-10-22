# 09 — Audit « Leopardo Public » (passerelle publique grand public)

**Dépôt :** `kitokoh/leopardo-hr` · **Base auditée :** `main` @ `c358f109a` (2026-10-19)
**Date :** 2026-10-20 · **Méthode :** lecture du code réel sur le commit cloné, appels à l'API
GitHub (branches, PR ouvertes), `grep` sur les routes/contrôleurs/providers. Aucune supposition
sur l'état des fonctionnalités : chaque affirmation porte une référence de fichier.

---

## 1. État initial constaté

| Domaine | Constat vérifié |
|---|---|
| Architecture | Monolithe modulaire Laravel 12 / PHP `^8.4.1`, DDD 5 couches, **37 modules** sous `api/app/Modules`, socle `api/app/Core`, contrats `api/app/Shared` |
| Registre BC | `dev-hub/governance/bounded-context-registry.json` — **37 BC** (BC-01 → BC-38, BC-37 absent du registre, BC-22 `planned`) |
| Multi-tenant | Schémas `public` + `shared_tenants`, scope `BelongsToCompany`, `TenantManager`, `PublicTenantResolver` (invariant public mutualisé) |
| Identité | Sanctum, 2FA, RBAC `api.manager:*` / `platform.permission:*`, policies par ressource, comptes acheteurs plateforme (`MarketplaceBuyer`, jeton `mkb_` + cookie HttpOnly) |
| Frontends | `front/web` (Next.js — landing + dashboard + verticales publiques), `front/marketplace`, `front/travel-web`, `front/admin-dashboard`, `front/web-offline`, `front/zkteco-kiosk`, 9 apps Flutter |
| Paiements | BC-38 `Modules/Paiements` + `Billing` + webhooks signés idempotents (Retail, Travel, Fundraising) |
| Notifications | `Modules/Notification` + contrats `Shared/Contracts/Notification/*` |
| Chat | `Modules/Notification` : `ConversationThread`, `ConversationMessage`, `ConversationParticipant`, `ConversationBlock`, `ConversationReport` (routes dans `api/routes/modules/rh.php`) |
| Géo | BC-34 `Modules/Geo` : `GeoServiceContract`, `PostgisDistanceCalculator`/`HaversineDistanceCalculator`, `SearchableRegistry` fail-closed |
| Travaux en cours | 6 PR ouvertes (#8732, #8721, #8668, #8642, #8573, #8572) — **aucune** ne porte sur la passerelle publique |

---

## 2. Découverte structurante — le backend « Leopardo Public » existe déjà

La lacune décrite comme « à construire » (une surface publique unifiée reliant le Business OS
aux clients grand public) **est déjà implémentée côté backend et fusionnée sur `main`** :

| Brique | Fichier | Rôle |
|---|---|---|
| Contrat partagé | `api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` | Chaque verticale expose SES entités déjà publiques (inversion de dépendance, garde d'isolation #5584) |
| Agrégateur | `api/app/Shared/Discovery/PublicDiscoveryService.php` | Collecte les providers tagués `public.discoverable`, déduplique `(type, slug)`, calcule les distances via `GeoServiceContract`, trie, pagine (`per_page` ≤ 50) |
| Contrôleur | `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PublicDiscoveryController.php` | `GET /api/v1/public/discover` — route isolée `throttle:shop-public`, sans auth ni middleware tenant |
| Providers | `RestaurantDiscoverableProvider`, `HospitalityDiscoverableProvider`, `ShowcaseDiscoverableProvider` | Fail-closed : `is_public=true`/`status=published`, société non `suspended`/`expired`, feature verticale active |
| Tests | `api/tests/Feature/Platform/PublicDiscoveryTest.php` | 7 tests / 32 assertions (multi-verticale, fail-closed, 422, tri distance, pagination) |
| Traçabilité | `CHANGELOG.md` (entrée `feat(superapp) … Closes #8611`), commit `a92e8129c` | Fusionné via PR #8613 |

**Conclusion :** il n'y a **aucun BC à créer**, **aucun module à reconstruire**, **aucune
verticale « Public » à inventer**. Le bon emplacement architectural est celui qui existe :
`Shared/Contracts/Discovery` + `Shared/Discovery` + providers par module.

---

## 3. Composants existants réutilisables (aucune recréation)

1. **`PublicDiscoveryService` / `PublicDiscoverableProvider`** — la surface de découverte
   cross-verticale, déjà testée, à consommer telle quelle.
2. **`PublicTenantResolver`** (`Shared/Services/PublicCommerce`) — invariant d'accès public
   (slug → société → gardes statut/feature → `withinTenant`), fail-closed 404 anti-énumération.
3. **Fiches publiques verticales déjà opérationnelles** — `/vitrine/{slug}` (BC-27),
   `/public/catalog/{companySlug}` (BC-28), `/public/market/*` (BC-17), `/public/restaurants`
   (BC-25), `/public/hospitality/*` (BC-32), `/public/travel/*` (BC-24), `/public/vtc/*` (BC-35).
4. **Chat** — `ConversationThread`/`ConversationMessage` + modération (blocage/signalement)
   déjà exposés côté Business OS.
5. **Notifications** — `Modules/Notification` + préférences.
6. **Géo** — `GeoServiceContract` (jamais de haversine ré-implémentée).
7. **Design system / i18n** — `front/web/src/lib/i18n.ts` (fr/en/ar/tr, RTL), tokens
   `docs/specifications/DESIGN_SYSTEM_TOKENS.md`.

---

## 4. Lacune réelle vérifiée (P0)

**Aucun frontend ne consomme `GET /api/v1/public/discover`.**

Preuve : `grep -rn "public/discover" front/ --include=*.ts --include=*.tsx` → **0 résultat**.
Chaque verticale conserve sa page silo (`/restaurants`, `/vitrine/[slug]`, `/[companySlug]`,
`/stay`, `/shop`, `/travel`) et le client doit **connaître la verticale avant de chercher**.
Le point d'entrée neutre existe côté API mais n'est **exposé à aucun utilisateur**.

C'est exactement l'écart entre « 8 vitrines verticales » et « une passerelle publique » :
la couche de composition manque, pas la couche de données.

### Périmètre retenu (frontend uniquement)

- Accueil public mobile-first : recherche (métier/service/nom), catégories réellement activées
  (dérivées de `meta.types` renvoyés par l'API), professionnels à proximité, états vide/erreur.
- Géolocalisation **avec consentement explicite** + **repli saisie manuelle ville/zone** si refus.
- Liste de résultats unifiée avec lien vers la **fiche publique verticale existante** (`item.url`).
- CTA contact/messagerie et CTA service/réservation **uniquement là où la verticale le prend en
  charge** ; sinon état explicite « action indisponible » (jamais de bouton inopérant).
- Aucune donnée interne exposée : le DTO public est déjà strict (pas d'`id`, `company_id`, stock,
  marge, `meta`).

### Hors périmètre (volontairement)

- Aucune nouvelle table, migration, index, rôle ou tenant.
- Aucune modification du backend (contrat, agrégateur, providers, routes) : réutilisation stricte.
- Pas de WebSocket / faux temps réel ; pas de paiement nouveau ; pas de portefeuille.

---

## 5. Risques identifiés

| Risque | Mitigation retenue |
|---|---|
| Fuite de données internes via le frontend | Le DTO public est déjà strict ; le frontend ne fait que l'afficher, aucun accès direct aux tables |
| Collecte de localisation sans consentement | Géoloc déclenchée par action utilisateur explicite uniquement, jamais en arrière-plan ; repli ville |
| Confusion avec le pointage géolocalisé employés | Surface strictement publique, sans auth ni contexte tenant |
| Bouton d'action inopérant | CTA conditionnés au `type` de la verticale ; état explicite sinon |
| Régression des parcours existants | Aucun fichier backend touché ; nouvelles routes frontend isolées |
| Conflit avec un autre agent | Aucune branche/PR existante sur ce périmètre (vérifié) ; branche dédiée |

---

## 6. Plan d'implémentation ciblé

| Étape | Contenu | Fichiers |
|---|---|---|
| 1 | Client API public + types | `front/web/src/lib/public-discovery-api.ts` |
| 2 | Accueil public (recherche, catégories, géoloc + repli ville, résultats) | `front/web/src/app/decouvrir/page.tsx` + composants |
| 3 | Tests frontend (rendu, états, filtres, liens) | `front/web/src/app/decouvrir/__tests__/` |
| 4 | Traçabilité (CHANGELOG, registre de scénarios) | `CHANGELOG.md`, `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` |

**Aucune migration. Aucun changement de configuration. Aucun secret.**

---

## 7. Statut par bloc

| Bloc | Statut |
|---|---|
| Phase 0 — Audit | ✅ Terminé (ce document) |
| Phase 1 — Issues + branche | ✅ Fait (issues GitHub + branche dédiée) |
| Phase 2 — Frontend passerelle publique | ✅ Implémenté |
| Phase 3 — Tests + qualité | ✅ Exécuté (résultats réels dans le rapport final) |
| Phase 4 — PR / fusion | ✅ PR ouverte, fusionnée sur `main` vert, branche supprimée |
