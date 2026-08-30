# Tunnel d'achat public TravelAgency (TRAVEL-1002, issue #6115)

**Statut : livré (lot 3, PR `feat/travel-batch3-remaining`).**

## Parcours (recette E2E automatisée)

```
Recherche → Réservation multi-passagers → Initiation paiement → Callback signé
→ Confirmation → Émission billet → E-billet PDF (accès par code) → Suivi
```

## Endpoints publics (jeton `X-Travel-Shop-Token`, middleware `travel.public.shop`)

| Étape | Endpoint | Notes |
|---|---|---|
| Recherche | `GET /api/v1/public/travel/shop/trips` | trajets publiés, throttling renforcé `shop-public` (30/min/IP) |
| Détail | `GET /api/v1/public/travel/shop/trips/{trip}` | 404 sûr cross-tenant |
| Réservation | `POST /api/v1/public/travel/shop/bookings` | source `online`, idempotente, contact + consentement |
| Paiement | `POST /api/v1/public/travel/payments/initiate` | contrat de passerelle existant (TRAVEL-408) |
| Callback | `POST /api/v1/travel/payments/callback` | public, HMAC, idempotent (TRAVEL-409) |
| E-billet | `GET /api/v1/public/travel/tickets/{ticket}/pdf?code=` | accès par code de validation, URL signée 30 min |
| Suivi | `GET /api/v1/public/travel/shop/bookings/{reference}?code=` | code de validation requis, données minimisées |

## Sécurité

- Jeton par tenant (hash SHA-256 seul persisté), rotation invalidante.
- Tenant résolu par le jeton ; scope `BelongsToCompany` fail-closed
  (`tenant_scope_required`) — aucune donnée cross-tenant (testé).
- Anti-bot : hook CAPTCHA configurable (`travel.public_shop.captcha_secret`) ;
  le tunnel complet est couvert par le test `TravelPublicTunnelTest`.

## Note frontend

Le tunnel est livré côté API + recette E2E backend. L'UI dédiée
(Next.js/PWA : recherche → panier → paiement → e-billet) relève du lot
frontend (TRAVEL-601..609/1008) — les contrats exposés ci-dessus sont
stables et testés.
