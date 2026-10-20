# C-1 — Élargissement de la découverte publique (VTC et Retail)

Issue : **#8773** · Branche : `feat/c1-public-discovery-vtc-retail`
Amont : rapport d'audit Phase 0, `docs/GESTION_PROJET/AUDIT_LEOPARDO_PUBLIC.md` (#8772)

## 1. Constat de départ

`GET /api/v1/public/discover` n'agrégeait que **3 verticales sur 37** :

| Type | Provider | Statut |
|---|---|---|
| `restaurant` | `RestaurantManager` | opérationnel |
| `hospitality_property` | `HospitalityManager` | opérationnel |
| `showcase` | `Showcase` | opérationnel |

Le contrat `PublicDiscoverableProvider` et son inversion par tag de conteneur
(`public.discoverable` / `public.profile_resolver`) existaient déjà : brancher
une verticale ne demandait **aucune** modification de `App\Shared\*`. La lacune
était un défaut de câblage, pas d'architecture.

## 2. Périmètre retenu — et pourquoi il a été réduit

Le plan d'audit visait quatre verticales. L'inspection du code en a éliminé
deux, sur constat vérifié et non sur préférence :

| Verticale | Opt-in public existant ? | Preuve | Décision |
|---|---|---|---|
| **VTC** | oui | `vtc_marketplace_operators.status = active`, contrôleur `VtcMarketplaceController::operators` (`GET /public/vtc/marketplace/operators`), service `VtcMarketplaceService::eligibleOperators()` | **branché** |
| **Retail** | oui | `retail_online_settings.enabled`, `RetailMarketplaceService::eligibleCompanyIds()`, `GET /public/market/sellers/{slug}` | **branché** |
| Pharmacy | **non** | aucune colonne `is_public`/`published`/`online` dans `pharmacy_products`, aucun contrôleur public | exclu |
| Trade | **non** | idem pour `trade_company_profiles` | exclu |

Exposer Pharmacy ou Trade aurait exigé d'**inventer** une sémantique de
publication que le métier n'a pas décidée. Fail-closed : elles restent absentes.

## 3. Ce qui a été implémenté

### VTC — `vtc_operator`

- `VtcDiscoverableProvider` — `search()` adossé à `VtcMarketplaceService::eligibleOperators()`
- `VtcProfileResolver` — même source de vérité, donc aucune divergence possible
  entre « apparaît dans la recherche » et « joignable au contact »
- Actions : **`contact` uniquement**. La course se demande par
  `POST /public/vtc/marketplace/rides` ; un bouton de réservation générique
  n'aurait aucun service derrière.

### Retail — `retail_shop`

- `RetailDiscoverableProvider` — `search()` adossé à `RetailMarketplaceService::eligibleCompanyIds()`
- `RetailProfileResolver` — slug public = `companies.slug` (celui déjà utilisé
  par `GET /public/market/sellers/{slug}`), nom public = `shop_name`
  (jamais la raison sociale)
- Actions : **`contact` + `order`** — `RetailMarketOrderPublicController`
  (`POST /public/market/orders`) sert réellement la commande en ligne.
  **Pas** `booking` : Retail ne prend pas de réservation.

### Enregistrement

Une ligne de tag par classe, dans le `boot()` du `ServiceProvider` de chaque
module — même patron que `Hospitality`/`Showcase`. `Shared/` n'importe toujours
aucun `App\Modules\*` (garde #5584 vérifiée : `check-layer-purity` → 0 violation).

## 4. Trois décisions fail-closed, documentées dans le code

1. **`url` = `null`** pour les deux verticales. La fiche boutique vit dans
   `front/marketplace`, dont l'origine n'est pas connue du backend ; un chemin
   relatif produirait un lien mort depuis `front/web`. On ne publie pas d'URL
   plutôt qu'une fausse.
2. **`latitude`/`longitude` = `null`** : ni l'opt-in VTC ni celui de Retail ne
   portent d'adresse géolocalisée. Une recherche `near` les exclut donc
   légitimement — aucune coordonnée n'est inventée.
3. **Aucune coordonnée privée** : `contact_phone`/`contact_email` de
   `retail_online_settings` ne sont **pas** publiées. Le client passe par le
   contact de la passerelle, qui notifie le professionnel.

## 5. Tests exécutés — résultats réels

Environnement : PHP 8.4, PostgreSQL 15 local, `CI=true`.

| Suite | Résultat |
|---|---|
| `VtcPublicDiscoveryTest` | **10/10** — 35 assertions |
| `RetailPublicDiscoveryTest` | **9/9** — 35 assertions |
| `PublicDiscoveryTest` (non-régression) | vert |
| `PublicContactRequestTest` (non-régression) | vert |
| `VtcMarketplacePublicApiTest` (non-régression) | vert |
| `RetailMarketPublicTest` (non-régression) | vert |
| **Total 4 suites** | **38/38 — 156 assertions** |

Qualité statique : `PHPStan` niveau 8 sur les 6 fichiers du lot → **`[OK] No errors`** ;
`pint --test` → **PASS**.

Chaque provider est testé sur : opt-in absent, feature coupée, société
suspendue, publication repassée inactive, **absence de fuite** (aucun
`company_id`/`id`/`stock`/coordonnée privée sérialisé), cohérence
recherche ↔ résolution, filtres `q`/`city`, et acceptation du nouveau
`target_type` par `POST /public/contact-requests`.

## 6. Ce qui reste

- **5 verticales sur 37.** Brancher les 32 autres dépend de leur disposition à
  publier un opt-in, pas de la passerelle — le coût d'ajout est d'un provider
  et d'une ligne de tag.
- **Aucun écran front dédié.** Les résultats apparaissent dans `/decouvrir`
  avec `url: null`, donc sans lien de fiche. Une fiche publique VTC reste à
  concevoir côté `front/web` ; la fiche boutique existe déjà dans
  `front/marketplace` mais son origine doit être exposée au backend pour être
  publiée dans `url`.
- **`eligibleOperators()` est appelé à chaque recherche** (pas de cache).
  Acceptable au volume actuel ; à mesurer avant d'ajouter un cache, qui
  introduirait un délai de dépublication.
