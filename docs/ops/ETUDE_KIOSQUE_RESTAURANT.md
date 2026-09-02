# Étude — Kiosque libre-service RestaurantManager (RESTO-807, #6228)

> Statut : **étude validée → implémentation v1 livrée** (web kiosque via les
> endpoints publics RESTO-805, source `kiosk`).
> Spec : `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` §12.
> Lot parent : RESTO-029 (#6156).

## 1. Besoin

Un kiosque en salle (écran tactile) permet au client de commander et payer
sans passer par le personnel : menu public par branche, panier, paiement
mobile money / carte, notification en cuisine.

## 2. Options matérielles

| Option | Matériel | Avantages | Limites | Verdict |
|---|---|---|---|---|
| A — Tablette + support | Tablette Android/iPad 10" + coque | Coût faible, déploiement immédiat | Vol, recharge | ✅ retenue pour le pilote |
| B — Borne dédiée | Écran tactile 15-22" industriel | Robustesse, impression ticket | Coût élevé | P2 |
| C — PC + écran | Mini-PC fanless + écran | Souple | Encombrement | P2 |

## 3. Mode connecté / offline

- **v1 (livré)** : kiosque connecté uniquement — le kiosque consomme les
  endpoints publics RESTO-805 (`GET /restaurant/public/menu`,
  `POST /restaurant/public/orders`, `POST /restaurant/public/orders/{order}/pay`)
  avec `source=kiosk`, sous un lien signé dédié (TTL court, 12 h, renouvelé
  par la direction). Aucune donnée client n'est stockée côté kiosque.
- **Offline (P2)** : file idempotente locale (pattern mobile leopardo_core) —
  la création de commande est idempotente par `idempotency_key`, donc un
  rejeu offline→online ne duplique jamais la commande. Non livré en v1.

## 4. Paiements

Réutilise le contrat `PaymentGatewayInterface` (RESTO-406/407) :
`mobile_money` (sandbox, confirmation par callback signé HMAC) en v1 ;
carte terminal en P2. Montants en minor units, jamais acceptés du client.

## 5. Flux v1 (implémenté)

1. La direction génère un lien signé par branche (`POST
   /restaurant/branches/{branch}/public-menu-link`, TTL 1..168 h).
2. Le kiosque charge le menu public (branches → menus → articles actifs).
3. Le client compose son panier → `POST /restaurant/public/orders`
   (`source=kiosk`, `consent` obligatoire) → commande `open`, décrément de
   stock, événement `restaurant.order.created.v1` → file cuisine.
4. Paiement → `POST /restaurant/public/orders/{order}/pay`
   (`provider_code=mobile_money`) → confirmation par callback signé.

## 6. Critères d'acceptation RESTO-807

- [x] Étude matériel/offline/paiements validée (ce document).
- [x] Implémentation web kiosque v1 (endpoints publics RESTO-805, source kiosk).
- [ ] Tests E2E kiosque (P2 — suite Playwright admin existante, extension
      prévue avec l'UI kiosque).

## 7. Risques & mitigations

- Lien signé volé → TTL court + régénération ; le lien n'expose que le menu
  public (aucune PII).
- Surdébit → montant recalculé serveur, paiement vérifié (RESTO-407).
- Rejeu webhook/paiement → idempotence par clés uniques (company_id,
  idempotency_key).
