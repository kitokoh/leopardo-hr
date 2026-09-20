# Leopardo Marché — Marketplace grand public (BC-17 RETAIL, phase 2)

> **Statut :** Spécification approuvée — fondateur/PM session 2026-09-19
> **Contexte :** BC-17 RETAIL (fondations livrées par #7718 : produits, stock, POS, espace web `/commerce`)
> **Épic :** voir issue EPIC « Leopardo Marché » (créée avec ce document)
> **Périmètre :** API publique cross-tenant, commandes en ligne invité, web client dédié `front/marketplace`, espace vendeur « Boutique en ligne »

---

## 1. Vision

Chaque vendeur Leopardo (boutique physique avec la verticale Retail active) doit pouvoir, en un clic,
**publier ses produits sur une vitrine marchande commune** — « Leopardo Marché » — où le grand public
achète en ligne comme sur Amazon : découverte, recherche, panier, commande, suivi, livraison à domicile.

Deux canaux de vente pour un même stock :

```text
Vendeur (tenant Retail)
├── Canal boutique physique  → POS v1 (#7674) — caisse, tickets, encaissement
└── Canal en ligne           → Leopardo Marché — commandes `online`, préparation, livraison
```

Principes non négociables :
- **Le tenant reste souverain** : publication en ligne opt-in produit par produit + réglage boutique opt-in.
- **Public vs privé strictement séparés** (pattern BC-27/BC-28) : les routes publiques n'exposent AUCUNE
  donnée interne (stocks chiffrés, marges, company_id, méta).
- **Totaux calculés côté serveur**, montants en minor units, idempotence à la création de commande.
- **Le stock ne s'écrit que par `RetailStockService`** (règle #7673) — la confirmation d'une commande en
  ligne décrémente (mouvements `sale`), l'annulation post-confirmation restaure (mouvements `return`).

## 2. Modèle de données (migrations tenant, sans FK, idempotentes)

### 2.1 `retail_online_settings` (nouvelle table — 1 ligne max par company)
| Colonne | Type | Note |
|---|---|---|
| company_id | uuid, unique, index | tenant |
| enabled | boolean default false | opt-in marketplace |
| shop_name | string | nom public de la boutique |
| shop_description | text nullable | |
| city | string nullable | affichée + filtre proximité v2 |
| contact_phone / contact_email | string nullable | |
| currency | string(3) default DZD | devise d'affichage publique |
| version | unsignedInteger default 1 | verrouillage optimiste |

### 2.2 `retail_products` (ajouts)
- `online_visible` boolean default false — publication marketplace (indépendant de `status`) ;
- `image_url` string nullable — visuel produit (URL absolue) ;
- Un produit n'est visible publiquement que si `status = published` **ET** `online_visible = true`
  **ET** la boutique du tenant est `enabled`.

### 2.3 `retail_orders` (ajouts — champs du canal `online`, nullables pour POS)
- `customer_name`, `customer_phone`, `customer_email` (nullable) ;
- `delivery_address`, `delivery_city`, `delivery_notes` (nullable) ;
- `fulfillment_status` string nullable — machine d'états en ligne :
  `pending → confirmed → ready → shipped → delivered` (+ `cancelled` terminal) ;
- `tracking_token` string(64) nullable, unique par tenant — jeton de suivi public ;
- `confirmed_at`, `shipped_at`, `delivered_at` timestamps nullable.

Machine d'états en ligne (le statut historique `RetailOrderStatus` reste la source du stock) :
- `pending` : commande reçue, stock non touché, `status = draft` ;
- `confirmed` : vendeur accepte → décrément stock (`sale`), `status = completed` ;
- `ready` / `shipped` / `delivered` : progression logistique (événements auditables) ;
- `cancelled` : depuis `pending` (aucun mouvement) ou après confirmation (mouvements `return`).

## 3. Contrat API

### 3.1 Public — `/api/v1/public/market/*` (sans auth, `throttle:shop-public`)
| Méthode | Route | Description |
|---|---|---|
| GET | `/public/market/products` | Recherche cross-tenant. Query : `q`, `seller` (slug), `category`, `min_price`, `max_price`, `sort` (`recent`\|`price_asc`\|`price_desc`), `page`, `per_page` (≤ 50). |
| GET | `/public/market/products/{id}` | Fiche produit publique (DTO public + vendeur). |
| GET | `/public/market/sellers` | Boutiques activées (nom, slug, ville, description, nb produits). |
| GET | `/public/market/sellers/{slug}` | Boutique + ses catégories publiques. |
| POST | `/public/market/orders` | Checkout invité — voir 3.2. Si un jeton acheteur valide est fourni (`Authorization: Bearer`), la commande est liée au compte (#7814). |
| GET | `/public/market/orders/{reference}` | Suivi public — exige `?token={tracking_token}` (404 fail-closed sinon). |
| GET | `/public/market/products/{id}/reviews` | Avis approuvés d'un produit public, paginés (`meta.rating_avg`, `meta.rating_count`) — #7814. |

DTO produit public : `id, name, description, price_minor, currency, image_url, category, seller{name, slug, city}, rating_avg, rating_count`.
Aucune quantité de stock exposée — seulement `available: bool` (niveau > 0 sur au moins un emplacement).
Les DTO boutiques exposent aussi `rating_avg` / `rating_count` (avis approuvés, agrégés par vendeur — #7814).

### 3.2 Checkout invité — `POST /public/market/orders`
```jsonc
{
  "seller": "boutique-slug",            // 1 commande = 1 vendeur (le panier multi-vendeurs est scindé côté client)
  "items": [{ "product_id": 12, "quantity": 2 }],   // 1..50 lignes, quantity 1..999
  "customer": { "name": "…", "phone": "…", "email": "…" },  // email optionnel
  "delivery": { "address": "…", "city": "…", "notes": "…" }, // notes optionnel
  "payment_method": "cash",              // v1 : paiement à la livraison (COD)
  "idempotency_key": "uuid-client"
}
```
Règles serveur : produits du vendeur uniquement, publiés + visibles en ligne, boutique activée,
prix relus en base (jamais confiance client), totaux serveur, `source = online`,
`fulfillment_status = pending`, `reference` générée (préfixe `WEB-`), `tracking_token` aléatoire (64 hex).
Réponse 201 : `{ reference, tracking_token, total_minor, currency, seller }`. Rejeu idempotent → 200 même payload.

### 3.2bis Comptes acheteurs, favoris & avis — `/public/market/account/*` (#7814, livré)

Comptes **PLATEFORME** (tables centrales du schéma `public` : `marketplace_buyers`,
`marketplace_buyer_tokens`, `marketplace_favorites`, `marketplace_reviews`) : la marketplace est
cross-tenant, un acheteur n'appartient à aucun vendeur. Authentification par **jeton opaque**
(`mkb_` + 64 hex, hash SHA-256 en base, TTL 30 j) — pas de Sanctum tenant. Toutes les routes
portent `throttle:shop-public` ; register/login un throttle strict dédié (`market-account-public`,
10/min/IP) et la soumission d'avis un throttle anti-spam (`market-reviews-public`, 5/min/IP).

| Méthode | Route | Description |
|---|---|---|
| POST | `/public/market/account/register` | Inscription légère : `name`, `email` (unique), `password` (≥ 8), `phone` optionnel → 201 `{ token, buyer }`. |
| POST | `/public/market/account/login` | Connexion → `{ token, buyer }` ; identifiants invalides → 401 uniforme. |
| POST | `/public/market/account/logout` | Révoque le jeton courant (idempotent). Auth buyer. |
| GET | `/public/market/account/me` | Profil public (`name`, `email`, `phone`, `created_at`). Auth buyer. |
| GET | `/public/market/account/orders` | Historique cross-tenant des commandes liées : `reference, seller{name,slug,city}, total_minor, currency, fulfillment_status, created_at, tracking_token, items[]`. Paginé. |
| GET | `/public/market/account/favorites` | Favoris → DTO produits publics (produits dépubliés filtrés fail-closed). |
| POST | `/public/market/account/favorites` | Ajout `{ product_id }` (produit public éligible, sinon 404) — idempotent. |
| DELETE | `/public/market/account/favorites/{productId}` | Retrait idempotent. |
| POST | `/public/market/account/reviews` | Avis vérifié `{ order_reference, product_id, rating 1..5, comment ≤ 1000 }` — autorisé UNIQUEMENT si la commande du buyer contient le produit ET est `delivered`. 1 avis par (buyer, commande, produit). Modération `pending|approved|rejected` (auto-approve v1). |

Liaison commande : `retail_orders.buyer_id` (nullable, sans FK — cross-schema). Le checkout
invité reste la norme ; un jeton invalide n'est jamais bloquant (la commande part en invité).

### 3.3 Vendeur — `/api/v1/retail/online/*` (middleware groupe retail existant)
| Méthode | Route | Description |
|---|---|---|
| GET / PUT | `/retail/online/settings` | Réglages boutique (create-or-update, RetailOnlineSettingsPolicy — principal/rh). |
| POST | `/retail/products/{id}/publish-online` · `/unpublish-online` | Bascule `online_visible` (RetailProductPolicy@publish). |
| GET | `/retail/online/orders` | Liste (filtres `fulfillment_status`, tri récent). |
| GET | `/retail/online/orders/{id}` | Détail. |
| POST | `/retail/online/orders/{id}/confirm` | `pending → confirmed` + décrément stock (emplacement principal). |
| POST | `/retail/online/orders/{id}/ready` · `/ship` · `/deliver` | Progression logistique. |
| POST | `/retail/online/orders/{id}/cancel` | Annulation (restaure le stock si déjà confirmée). |

Transitions invalides → 422 `INVALID_TRANSITION`. Toutes les actions passent par des Policies + audit.

## 4. Web client dédié — `front/marketplace`

Nouvelle application Next.js (App Router) **indépendante de `front/web`** — c'est le « web client grand
public » : aucune session tenant, aucun cookie d'auth, direct-to-API (`NEXT_PUBLIC_MARKET_API_BASE`).

Pages v1 : accueil (héros + nouveautés + boutiques), `/produits` (recherche/filtres/tri/pagination),
`/produits/[id]`, `/boutiques`, `/boutiques/[slug]`, `/panier` (localStorage, groupé par boutique),
`/commande` (checkout invité, 1 commande créée par boutique), `/confirmation`, `/suivi` (référence + jeton).

Compte acheteur (#7814, livré) : `/compte` (inscription/connexion, jeton en localStorage),
`/compte/commandes` (historique + formulaire d'avis quand la commande est livrée),
`/compte/favoris` ; bouton favori sur les cartes produit et la fiche ; note moyenne + section
avis vérifiés sur `/produits/[id]`.

Exigences : FR par défaut, mobile-first, SEO (metadata + OG), design soigné (Tailwind 4), états
vides/erreurs/chargement traités, accessibilité (focus visibles, aria), zéro dépendance lourde.

## 5. Espace vendeur `/commerce` (front/web)

Nouvelle page `commerce/boutique` : activation + réglages de la boutique en ligne, bascule
« En ligne » par produit, liste des commandes web avec actions (confirmer, prête, expédiée, livrée,
annuler). i18n 4 locales (fr/en/ar/tr) via les registres partagés, patterns #7675.

## 6. Hors périmètre v1 (issues backlog dédiées)
- Handoff **BC-26 Delivery** automatique (`RetailOnlineOrderConfirmed` → création de livraison + tracking partagé) ;
- ~~Comptes acheteurs, favoris, avis & notations~~ — **livré par #7814** (voir §3.2bis) ;
  reste en backlog : modération des avis (v1 = auto-approve, champ statut déjà présent),
  réinitialisation de mot de passe, fusion de l'historique invité ;
- Reçus/factures PDF (POS + web) ;
- Recherche à facettes/geo, promotions, frais de livraison paramétrables.

> Le **paiement en ligne réel** (mobile money / PSP), initialement hors périmètre, est livré par
> l'issue #7812 — voir §8.

## 7. Sécurité & conformité
- Public : throttle `shop-public`, DTO fail-closed, jeton de suivi obligatoire, 404 par défaut ;
- Idempotence création commande (clé unique par tenant) ;
- RGPD : données client minimales, pas d'email obligatoire, mentions sur la page checkout ;
- Tests Feature obligatoires par endpoint : parcours nominal, isolation tenant, opt-in respecté,
  transitions d'états, idempotence, jeton invalide.

## 8. Paiement en ligne (BC-21 pragmatique — issue #7812)

Le checkout public accepte `payment_method: "cash" | "online"` — **COD (`cash`) reste le défaut**.

### 8.1 Modèle de données
- **`retail_online_payment_intents`** (tenant, sans FK, idempotente) : `company_id`, `order_id`,
  `intent_reference` (64 hex, unique par tenant — aléatoire 256 bits, résolution cross-tenant du
  webhook), `provider` (`chargily|mock`), `amount_minor`, `currency`, `status`
  (`pending|processing|succeeded|failed|expired|refunded`), `checkout_url` nullable,
  `provider_payload` json nullable (trace auditable), `idempotency_key`, timestamps ;
- **`retail_orders`** (ALTER idempotent) : `payment_method` (`cash|online`), `payment_status`
  (`pending|paid|refunded`), `paid_at`. Au succès, une trace d'encaissement `RetailOrderPayment`
  (`method = online`, `status = captured`) est enregistrée — cohérence avec le POS #7674.

### 8.2 Abstraction provider (locale au module Retail)
`Domain/Contracts/RetailPaymentProviderInterface` (`createIntent`, `verifyWebhookSignature`,
`parseWebhookEvent`, `verifyIntent`, `refund`) + `Infrastructure/Payments/` : **ChargilyProvider**
(Chargily Pay v2 — mobile money EDAHABIA / carte CIB, `POST /api/v2/checkouts`, webhook HMAC-SHA256
header `signature`, même intégration que Accounting #5272) et **MockProvider** (tests/dev).
Sélection par `config('retail.payments.provider')` + credentials env (`RETAIL_PAY_CHARGILY_SECRET`,
`RETAIL_PAY_CHARGILY_WEBHOOK_SECRET`, `RETAIL_PAY_MOCK_WEBHOOK_SECRET`) — **aucun secret en dur**.
⚠️ Les profils de paiement tenant **BC-21** (PR #7732) ne sont PAS mergés : cette config env est le
fallback prévu, la résolution par tenant se branchera dans `RetailPaymentProviderRegistry` sans
changer les appelants.

### 8.3 Contrat API
| Méthode | Route | Description |
|---|---|---|
| POST | `/public/market/orders` | `payment_method: cash\|online`. Si `online` : réponse enrichie `payment: { method, intent_reference, status, checkout_url }` (rejeu idempotent → même intent). Provider indisponible → 422 `PAYMENT_PROVIDER_UNAVAILABLE` (la commande existe, rejeu possible). |
| POST | `/public/market/payments/webhook/{provider}` | Webhook signé **fail-closed** : signature HMAC obligatoire sur le corps brut (invalide/secret absent → 401, provider inconnu → 404), idempotent (rejeu → 200 sans double effet). `succeeded` → intent + commande payée (`paid_at`, trace RetailOrderPayment) ; `failed`/`expired` → statut correspondant, commande **non payée**. Contrôle anti-fraude du montant notifié. |
| GET | `/public/market/orders/{reference}` | Suivi public : expose en plus `payment: { method, status }` — fail-closed, rien d'autre. |
| POST | `/retail/online/orders/{id}/refund` | Vendeur (RetailOrderPolicy@pay, principal/rh) : uniquement si intent `succeeded` (422 `PAYMENT_NOT_REFUNDABLE` sinon), appelle `provider->refund`, statut `refunded` + trace auditable. |

### 8.4 Réconciliation
`php artisan retail:payments:reconcile [--minutes=30]` : re-vérifie auprès du provider les intents
`pending|processing` plus vieux que X minutes (défaut `RETAIL_PAY_RECONCILE_AFTER_MINUTES`) et
applique les **mêmes transitions** que le webhook (voie unique `RetailPaymentService::applyStatus`,
transactionnelle + verrou de ligne). À planifier toutes les 15–30 minutes.

### 8.5 Web client (`front/marketplace`)
Checkout : choix « Paiement à la livraison » / « Paiement en ligne » ; en ligne, une seule boutique
→ redirection vers la `checkout_url` du PSP, plusieurs boutiques → bouton « Payer en ligne » par
commande sur `/confirmation`. Page **`/paiement/retour?reference=`** : le jeton de suivi est relu en
localStorage (jamais transmis au PSP), poll du statut de paiement (payé/en attente/remboursé/erreur),
FR, mobile-first. `/suivi` affiche l'état du paiement.
