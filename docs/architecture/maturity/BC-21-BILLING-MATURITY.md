# BC-21 — Billing & Subscription — Rapport de maturité (DEP-BC21)

- **Statut :** PARTIAL → corrections livrées (#5897)
- **Date :** 2026-08-29
- **Agent propriétaire :** 21 (Billing & Subscription)
- **Référentiel :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` §BC-21
- **Périmètre :** plans, abonnements, entitlements, paiements, facturation plateforme

## Cartographie de l'existant

| Brique | Composant |
|---|---|
| Webhooks providers | `StripeWebhookController` (signature vérifiée, `checkout.session.completed`/`invoice.paid`/`customer.subscription.*`), `PaymentWebhookController` (Chargily, signature) — **idempotence persistée** `WebhookEventRegistry` (#5444) |
| Abonnements | `Subscription` (plan, statut, période, stripe/chargily ids), `CreateSubscription`, `CancelSubscriptionRequest` |
| Plans/features | `Feature`, `FeaturePlanMatrix`, `PlanCode`, activation feature flags par plan |
| Facturation | `Invoice` (statuts pending/overdue/paid), `billing:check-overdue` |
| Partenaires | `PartnerService` (transitions gardées #3859), références |
| Paiements | `Payment` (statuts, refunds), `ProcessCommissionOnPayment` |

## Audit des douze dimensions

| Dim | Statut | Preuve / Lacune |
|---|---|---|
| D1 Domaine | **PARTIAL→CORRIGÉ** | Vocabulaire partiel ; **lacune corrigée : machine à états des abonnements explicite** (`SubscriptionStatus`, transitions gardées) |
| D2 Données | **PRESENT** | Tables billing tenant, index, périodes datées |
| D3 Tenant | **PRESENT** | `BelongsToCompany`, abonnement par entreprise |
| D4 API | **PARTIAL** | `BillingController` + Requests ; OpenAPI partielle |
| D5 Autorisation | **PRESENT** | Contrôleurs authentifiés, policies d'abonnement |
| D6 Transactions | **PRESENT** | Idempotence webhooks (registre #5444) — un webhook rejoué ne double pas une souscription (testé `WebhookIdempotenceTest`) |
| D7 Asynchronisme | **PARTIAL** | `DispatchWebhook`, jobs de notification ; pas de file dédiée billing |
| D8 Sécurité | **PRESENT** | Signatures vérifiées, SSRF guard (`NotPrivateUrl`), secrets via .env |
| D9 Frontends | **PARTIAL** | Portail essai/plans ; état de facturation dans l'admin |
| D10 Performance | **PARTIAL** | Pas de budget dédié billing |
| D11 Exploitation | **PARTIAL→CORRIGÉ** | `billing:check-overdue` ; **lacune corrigée : politique de recouvrement explicite** `billing:enforce-delinquency` (actif expiré → past_due, past_due hors grâce → suspended, idempotente) |
| D12 Produit | **PARTIAL** | Parcours essai → abonnement ; recette pilote |

## Corrections livrées dans cette PR

1. **Machine à états des abonnements (D1)** :
   - `SubscriptionStatus` (trial/active/past_due/expired/cancelled) aligné sur
     la contrainte `subscriptions_status_check`, avec `allowedTransitions()` ;
     les transitions couvrent tous les flux produit existants : renouvellement
     (`cancelled → active`, `POST /billing/subscription/renew`), résiliation
     depuis `past_due`/`expired`, récupération `expired → active` ;
   - `Subscription::transitionTo()` — toute transition invalide lève
     `InvalidArgumentException` (ex. `trial → past_due` refusé — la politique
     de recouvrement passe par `expired`).
   - **Bug corrigé (D2)** : StripeService écrivait `unpaid` (violation de
     `subscriptions_status_check` en production) → mappé sur `past_due`.
2. **Politique de recouvrement explicite (D11)** — `billing:enforce-delinquency` :
   - phase 1 : `active` à période expirée → `past_due` ;
   - phase 2 : `past_due` au-delà du délai de grâce (`--grace-days`, défaut 7)
     → `expired` (l'enforcement opérationnel de l'accès reste porté par
     `companies.status`) ;
   - **idempotente** (rejouable) — un tenant en défaut est traité selon une
     politique explicite (exigence backlog).
   Tests `BillingDelinquencyPolicyTest` (7) : matrice de transitions, refus de
   transition invalide, récupération suspended→active, expiration→past_due,
   suspension hors grâce, grâce respectée, idempotence.

## Sortie exigée par le backlog

- [x] Un webhook rejoué ne double pas une souscription (idempotence #5444 testée)
- [x] Un paiement ne débloque pas un module hors entitlement (feature flags par plan)
- [x] Un tenant en défaut est traité selon une politique explicite (**nouveau** :
    actif expiré → past_due → expired après grâce ; récupération → active)

## Reste à faire (hors périmètre de cette PR courte)

- Migrer les écritures `status` directes de `StripeService` vers `transitionTo()`
- Prorata et grace period liée aux invoices
- Réconciliation paiements/invoices (rapprochement Stripe ↔ factures)
