# Kiosque libre-service RestaurantManager — Étude puis implémentation

> **RESTO-807 (#6228)** — Lot parent RESTO-029. Statut : étude validée → implémentation v1 web.
> BC-25 RESTAURANT · spec `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` (§12 kiosque).

## 1. Objectif

Un terminal libre-service (kiosque) permet au client de commander **sans
passer par un serveur** : consultation du menu public du tenant, panier,
passage de commande et paiement. Le kiosque consomme **uniquement** la
boutique publique RestaurantManager (RESTO-805/#6226) — pas d'API
authentifiée, pas de session utilisateur.

## 2. Matériel (étude)

| Critère | Recommandation v1 | Notes |
|---|---|---|
| Terminal | Tablette Android 10"+ / mini-PC, écran tactile ≥ 10" | Navigateur Chromium plein écran (`--kiosk`) |
| Résolution | 1280×800 minimum | Interface grande taille, cibles tactiles ≥ 48 px |
| Connectivité | Wi-Fi/Éthernet ; **mode dégradé hors-ligne** (voir §4) | Le kiosque fonctionne sans réseau au prix d'un panier restreint |
| Périphériques | Aucun requis en v1 | Impression ticket = option (PDF/URL signée, BC-20) |
| Sécurité physique | Câble Kensington + verrouillage navigateur (URL fixe) | Le kiosque n'expose que `/kiosk?token=…` |

## 3. Flux utilisateur (v1)

1. Le gérant provisionne le jeton de boutique (`POST /restaurant/public-shop-token/rotate`)
   et configure l'URL du kiosque : `https://<portail>/kiosk?token=<jeton>`.
2. Le client parcourt le menu public (catégories → produits actifs/disponibles).
3. Panier : ajout/retrait de produits (quantités), total recalculé côté serveur.
4. Validation : `POST /public/restaurant/orders` (idempotent, `source=online`).
5. Paiement : espèces (confirmé immédiatement) ou mobile money (pending →
   confirmé par callback signé RESTO-407).
6. Écran de confirmation : référence de commande (suivi `GET /public/restaurant/orders/{reference}`),
   notification cuisine (outbox `restaurant.order.created.v1` → file cuisine).

## 4. Mode hors-ligne (étude — différé v1.1)

Le kiosque v1 exige le réseau pour le menu et la commande. Le mode dégradé
v1.1 (recommandé, hors périmètre de cette issue) :

- cache applicatif (menu du jour) via service worker / stockage local ;
- file locale d'ordres avec rejeu idempotent (`idempotency_key` par ordre) ;
- paiement espèces uniquement hors-ligne (pas de confirmation en ligne) ;
- réconciliation au retour réseau (mêmes invariants : prix serveur).

## 5. Paiements (étude)

| Mode | v1 | Confirmation | Note |
|---|---|---|---|
| Espèces | ✅ | Immédiate (CashPaymentGateway) | Encaissement constaté par le client au kiosque |
| Mobile money | ✅ | Pending → callback signé (RESTO-407) | Sandbox ; production = provider configuré |
| Carte | ⏳ | Terminal requis | Hors périmètre v1 (étude matériel) |

## 6. Sécurité (étude)

- **Tenant** : jeton `rshop_…` (hash SHA-256 en base) passé en query param —
  le kiosque n'accède qu'au menu/commandes de SON tenant (scope
  BelongsToCompany, fail-closed). Rotation = invalidation immédiate.
- **Anti-scraping** : throttling `restaurant-shop-public` (30/min/IP) +
  hook CAPTCHA optionnel (config `public_shop.captcha_secret`).
- **Aucun secret client** : le jeton n'est jamais loggé ; les montants sont
  toujours recalculés serveur (aucun montant accepté du client).
- **RGPD** : pas de donnée personnelle collectée en v1 (aucun compte client) ;
  journal d'audit des commandes côté serveur.

## 7. Décisions

| # | Décision | Justification |
|---|---|---|
| D1 | Kiosque = page web du portail client (`front/web/src/app/kiosk`) | Réutilise i18n/RTL/design system, testable (Jest + Playwright) |
| D2 | Consomme exclusivement l'API publique RESTO-805 | Aucun chemin authentifié sur un terminal public |
| D3 | `order_type` par défaut = `takeaway` (choix possible : livraison) | Cohérent avec le flux self-service |
| D4 | Hors-ligne différé v1.1 | Réduit le périmètre critique (paiements hors-ligne) |

## 8. Critères d'acceptation

- [ ] `/kiosk?token=…` affiche le menu public du tenant (aucune donnée cross-tenant).
- [ ] Commande complète (panier → ordre → paiement espèces) sans authentification utilisateur.
- [ ] Jeton absent/invalide → message d'erreur explicite, aucune donnée affichée.
- [ ] Tests unitaires (Jest) + E2E Playwright (RESTO-902).

## 9. Références

- RESTO-805 (#6226) — API boutique publique (menu, commande, paiement).
- RESTO-902 (#6231) — E2E Playwright (kiosque inclus).
- `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` §6.1/§6.2/§12.
