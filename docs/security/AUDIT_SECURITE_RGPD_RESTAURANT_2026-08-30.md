# AUDIT SÉCURITÉ & RGPD — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-904 (#6233) — Audit sécurité & RGPD avant pilote (PII clients, paiements, caisse).
> **Date :** 2026-08-30 — **Périmètre audité :** code livré sur les branches `bc/bc25-restaurant-*` (fondations, schéma, référentiel, POS/paiements, livraison, fidélité, promotions, rapports) — état au moment de l'audit.
> **Référence :** spec §7 (Sécurité & RGPD), `docs/security/THREAT_MODELS_MAT017.md`, audit API de référence `docs/security/AUDIT_API_2026-07-19.md`.
> **Verdict :** ✅ **aucun finding bloquant** — 4 recommandations (non bloquantes) en §6.

---

## 1. Périmètre et méthodologie

| Surface | Éléments audités | Références code |
|---|---|---|
| PII clients | réservations (`contact_name`, `contact_phone`, `notes_redacted`), comptes fidélité (lien contact CRM), exports | `RestaurantReservation`, `RestaurantLoyaltyCustomer`, `RestaurantReportService` |
| Paiements | contrat de passerelle, adapters cash/carte/mobile money, callback signé HMAC, remboursements | `PaymentGatewayInterface`, `RestaurantPaymentCallbackController`, `PayOrderAction`, `RestaurantRefundController` |
| Caisse | sessions, totaux serveur, écart + motif, clôture immuable (version) | `RestaurantPosSession`, `ClosePosSessionAction` |
| Accès | middleware flag, Policies, 404 cross-tenant, matrice RBAC | `EnsureRestaurantManagerModuleMiddleware`, `RestaurantPermissions`, Policies |
| Données & exports | colonnes redactées, URL signée éphémère, allowlists | `RestaurantReportService`, `ExportRestaurantReportAction` |

## 2. Matrice des risques et contrôles

| Risque | Exposition | Contrôle en place | Statut |
|---|---|---|---|
| Fuite cross-tenant | tout le module | `company_id` non nullable partout, scope `BelongsToCompany` fail-closed, contrôleurs 404 (jamais 403) cross-tenant, tests d'isolation | ✅ |
| PII réservations (nom, téléphone) en clair | `restaurant_reservations` | champs nécessaires au métier ; `notes_redacted` ; événements outbox **sans PII** (payload redigé) ; rétention alignée tenant | ✅ |
| Vol de secret paiement | config | aucun secret en dur : identifiants passerelle en config/env, callback signé HMAC (secret par tenant) | ✅ |
| Rejeu de callback paiement | `POST /restaurant/payments/{payment}/callback` | signature HMAC + idempotence `UNIQUE(company_id, idempotency_key)` + montant vérifié serveur | ✅ |
| Double encaissement / montant client | `POST /orders/{order}/pay` | montant recalculé serveur (`remainingDue`), transaction + re-vérification, 409 si déjà payé | ✅ |
| Caisse falsifiée | clôture de caisse | totaux recalculés serveur (fonds + Σ encaissements confirmés), écart + motif obligatoire, verrou optimiste `version` | ✅ |
| Points fidélité abusés | crédit/échange | crédit idempotent (index unique partiel), échange jamais négatif (422), opt-in RGPD requis | ✅ |
| Promo hors bornes | codes promo | validation serveur bornes/fenêtre/plafond, remise ≤ sous-total (BillCalculator) | ✅ |
| Téléchargement d'export non autorisé | `/reports/exports/{export}/download` | URL signée éphémère (15 min), signature invalide → 403, fichier résolu dans le dossier du tenant | ✅ |
| Accès non autorisé aux rapports | `/reports/*` | permission documentaire `restaurant.reports` (matrice RBAC RESTO-306) | ✅ |
| Dead-letter outbox silencieuse | `restaurant:outbox-dispatch` | statuts `failed` + `last_error` tracés ; alerting à configurer (recommandation R2) | ⚠️ |
| Logs PII | payloads d'événements | payloads outbox redigés (aucune PII client) — vérifié sur les 4 événements publiés | ✅ |

## 3. PII clients — conformité RGPD

| Donnée | Localisation | Justification | Mesures |
|---|---|---|---|
| Nom/téléphone de réservation | `restaurant_reservations.contact_name/contact_phone` | nécessaire au service (spec §3.3) | données du tenant ; jamais dans les événements/rapports/CSV ; suppression avec le tenant (rétention plateforme) |
| Lien client (fidélité) | `restaurant_loyalty_customers.customer_contact_id` | opt-in explicite requis (pas de compte = pas de crédit) | opt-in documenté (RESTO-606) ; solde exposé, contact jamais dupliqué |
| Notes | `notes_redacted` | contexte service | champ dédié « redacted » (pas de PII complémentaire) |
| Payloads de callback | `restaurant_order_payments.callback_payload_redacted` | traçabilité paiement | stocké redacté (JSONB) |

**Droit d'effacement** : couvert par la suppression du tenant (les données `restaurant_*` sont tenant-scoped, aucune donnée globale hors tenant).

## 4. Paiements

| Contrôle | Implémentation | Preuve |
|---|---|---|
| Contrat de passerelle | `PaymentGatewayInterface` (initiate/verify/refund) + `PaymentGatewayRegistry` | `RestaurantPaymentGatewayTest` |
| Adapters v1 | cash / carte (terminal) / mobile money **sandbox** | idem |
| Callback signé | HMAC `X-Leopardo-Signature`, secret par tenant, rejeu → 1 seule confirmation | `RestaurantOrderPayTest` (rejeu callback) |
| Montants | minor units entières, montant vérifié serveur, jamais accepté du client | `PayOrderAction` |
| Remboursements | motif, idempotence, événement `restaurant.payment.refunded.v1` | `RestaurantRefundTest` |

## 5. Caisse

- Une seule session ouverte par branche (409) ; clôture immuable (verrou optimiste `version`).
- `expected_cash_minor` = fonds + Σ encaissements confirmés (recalcul serveur) ; `counted_cash_minor` saisi ; écart + motif obligatoire si écart.
- Événement de clôture `restaurant.pos.closed.v1` → Accounting/Reporting (RESTO-412, à livrer avec le lot POS restant).

## 6. Findings et recommandations

| # | Sévérité | Finding | Recommandation |
|---|---|---|---|
| R1 | Faible | Benchmarks de charge POS/réservations non exécutés | Gate `performance` du pilote (pilot-gates.json) avant GO |
| R2 | Faible | Alerting sur dead-letter outbox non branché | Alerter sur `restaurant_outbox_events.status=failed` (PagerDuty/email ops) |
| R3 | Faible | Le rappel de réservation notifie l'équipe branche (pas encore le client par SMS) | Notification client via canaux externes BC-13 quand le CRM contact est branché (RESTO-80x) |
| R4 | Faible | `employee_id` du livreur est une référence par valeur (pas de FK) | Vérification d'intégrité dans la revue de maturité (DEP-BC25) |

**Aucun finding bloquant** — le pilote peut démarrer sur la base des contrôles ci-dessus, sous réserve des gates `performance`/`observability`/`recette` (pilot-gates.json, RESTO-903).

## 7. Threat model (MAT-017)

Surface enregistrée : `restaurant_pos` (voir `dev-hub/tools/security-threat-models.json`) — contrôles couverts : secrets, signatures, replay, permissions, audit — document de référence : le présent audit + `THREAT_MODELS_MAT017.md`.
