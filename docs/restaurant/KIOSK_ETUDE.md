# Étude & implémentation — Kiosque libre-service RestaurantManager (RESTO-807, issue #6228)

> **Statut : étude livrée + contrats backend livrés** (v1). L'implémentation
> web kiosque consomme les contrats publics de la boutique (RESTO-805) ;
> une nouvelle surface front dédiée sera branchée sur ces contrats (le gel
> 60 jours `FREEZE_SCOPE_60J.md` limite les nouvelles apps — ce livrable
> reste dans le périmètre backend autorisé de la verticale BC-25).

## 1. Contexte

Le kiosque libre-service permet au client de commander et payer **sans
intervention d'un employé**, sur un poste fixe en salle (borne tactile ou
tablette). Il doit rester utilisable pendant les pics de service et les
micro-coupures réseau.

## 2. Étude — matériel

| Exigence | Recommandation |
|---|---|
| Terminal | Tablette 10" (Android) ou borne tactile 15" ; web app offline-first servie en local (PWA `front/zkteco-kiosk` pattern) |
| Imprimante tickets | Reçue via la commande (`ticket_number` court) ; impression thermique locale (80 mm) ou ticket virtuel sur écran |
| Paiement | V1 : espèces encaissées à la caisse (instruction `pay_at_pickup`) + mobile money en ligne (sandbox, callback signé HMAC) ; terminal carte → v2 (contrat `PaymentGatewayInterface` déjà prêt) |
| Réseau | Fonctionnement dégradé garanti : la commande est créée côté serveur dès que le réseau revient (file idempotente, RESTO-804) |

## 3. Étude — sécurité & RGPD

- Aucun identifiant client requis pour commander (anonymisation) ; le
  téléphone (optionnel) est tronqué au stockage (`note_redacted`).
- Le kiosque s'authentifie par **jeton signé par tenant** (`X-Restaurant-Shop-Token`,
  hash SHA-256 en base, rotation possible) — aucune fuite cross-tenant
  (scope `BelongsToCompany`, fail-closed 401).
- Rate limiting renforcé (`throttle:shop-public`, 30/min/IP) + hook
  anti-bot CAPTCHA configurable (`restaurantmanager.public_shop.captcha_secret`).
- Montants 100 % côté serveur (prix du référentiel, `BillCalculator`) —
  jamais acceptés du client.

## 4. Contrats backend v1 (livrés)

| Besoin | Endpoint | Note |
|---|---|---|
| Menu kiosque | `GET /api/v1/public/restaurant/kiosk/menu` | produits disponibles du tenant |
| Commande kiosque | `POST /api/v1/public/restaurant/kiosk/orders` | retourne `reference` + `ticket_number` |
| Suivi | `GET /api/v1/public/restaurant/kiosk/orders/{reference}` | statut + total |
| Menu boutique (partagé) | `GET /api/v1/public/restaurant/shop/menu` | idem |
| Paiement | `POST /api/v1/public/restaurant/shop/orders/{reference}/pay` | cash (à l'encaissement) ou mobile money |

Tests : `RestaurantKioskTest` (création + ticket, isolation cross-tenant,
jeton invalide → 401).

## 5. Implémentation (étapes restantes, hors gel)

1. Front web kiosque (PWA offline-first) consommant les contrats ci-dessus.
2. Impression de ticket (BC-20 Documents, fallback disque + URL signée).
3. Terminal carte au kiosque (adapter `CardPaymentGateway`).
4. E2E kiosque complet (déjà couvert au niveau API par `RestaurantKioskTest`).

## 6. Décision

**V1 validée pour les contrats backend + étude** ; le front kiosque dédié
est séquencé après le gate J60 (gel `FREEZE_SCOPE_60J.md`) ou via
exception fondateur (`[FREEZE-EXCEPTION]`).
