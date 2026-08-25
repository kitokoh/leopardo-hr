# Feature Specification: Paiement en ligne des documents — portail client (#5272)

**Feature Branch**: `mod/accounting/5272-gateway-adr`

**Created**: 2026-08-24

**Status**: Spec — prête à coder dès la décision fondateur (ADR-0017)

**Input**: Issue #5272 — [P2][conversion] Comptabilité 100 % — paiement en ligne portail
client (passerelle, décision). Références : ADR-0017
(`docs/architecture/adr/0017-paiement-en-ligne-portail-client.md`), conception
`docs/architecture/COMPTABILITE_CONCEPTION.md`, socle data #5221, workflow documents #5223,
portail client #5357, idempotence #5277.

## Décision (ADR-0017, en attente d'approbation fondateur)

**Option A — dual-PSP piloté par pays entreprise** : `PaymentGatewayInterface` commune ;
DZ → Chargily (plan Comfort 1,25 %), FR/UK/US/CI → Stripe (Checkout Session) ; MA/TN/TR en
phase 2 via la même interface. PayPal DZ écarté (réception non fiable).

## User Scenarios & Testing

### US1 — Le client paie une facture en ligne depuis le portail (Priority: P1)

Le client ouvre le lien sécurisé du document (#5357), clique « Payer en ligne », est
redirigé vers la passerelle (Chargily si entreprise DZ, Stripe sinon), paie (CIB/Edahabia ou
carte), revient au portail avec un statut à jour.

**Why this priority** : c'est le parcours de conversion cible (#5272 DoD : « un paiement en
ligne pilote rapproché sans intervention »).

**Independent Test**: `POST /api/v1/accounting/documents/{id}/checkout` (portail ou
comptable/principal) renvoie `{checkout_url, expires_at}` ; le webhook simulé de la passerelle
rapproche le paiement.

**Acceptance Scenarios**:

1. **Given** un document `sent` d'une entreprise DZ, **When** le portail demande le checkout,
   **Then** `302/JSON` vers l'URL Chargily (`method=online_chargily` attendu au rapprochement).
2. **Given** un document d'une entreprise FR/UK/US/CI, **When** le portail demande le checkout,
   **Then** Checkout Session Stripe (pattern `StripeService` existant).
3. **Given** un document non émis (draft) ou annulé, **When** le portail demande le checkout,
   **Then** `422 DOCUMENT_NOT_SENDABLE` (règles #5223 préservées).
4. **Given** une entreprise sans passerelle configurée, **When** le portail demande le checkout,
   **Then** `422 PAYMENT_GATEWAY_NOT_CONFIGURED` (fail-closed).

### US2 — Le webhook de la passerelle rapproche le paiement automatiquement (Priority: P1)

`POST /api/v1/accounting/payments/webhook/{gateway}` vérifie la signature (HMAC, fail-closed),
crée `AccountingPayment` (idempotent) et met le document à jour.

**Independent Test**: webhook simulé signé → `AccountingPayment` créé (montant TTC, devise,
`gateway_payment_id`), document → `paid` (ou `partially_paid`) ; replay du webhook → aucun
double paiement.

**Acceptance Scenarios**:

1. **Given** un webhook valide (signature HMAC OK), **When** il est reçu, **Then** `200` +
   `AccountingPayment` créé + document mis à jour (règles #5223).
2. **Given** un webhook non signé ou mal signé, **When** il est reçu, **Then** `401` +
   aucun traitement (fail-closed, pattern `ChargilyService` #2615).
3. **Given** un replay du même webhook (`Idempotency-Key` identique ou `gateway_payment_id`
   déjà connu), **When** il est reçu, **Then** `200` rejoué sans nouveau paiement.
4. **Given** un montant qui ne matche pas le TTC du document, **When** il est reçu,
   **Then** refus + alerte (anti-fraude montant≠), aucun rapprochement.
5. **Given** un paiement partiel, **When** il est rapproché, **Then** document →
   `partially_paid` (règles #5223) et relance pour le solde.

### US3 — L'échec ou l'expiration ne casse pas le document (Priority: P2)

**Independent Test**: paiement annulé/expiré → document reste `sent`, aucun `AccountingPayment`
fantôme.

**Acceptance Scenarios**:

1. **Given** un paiement annulé par le client, **When** la passerelle notifie,
   **Then** document `sent` inchangé, pas de paiement créé.
2. **Given** une session de checkout expirée, **When** le client revient,
   **Then** le portail propose un nouveau checkout (nouvel `Idempotency-Key`).

## Critères d'acceptation (DoD #5272 — après décision)

- [ ] Un paiement en ligne **pilote DZ** (CIB/Edahabia via Chargily) rapproché **sans
      intervention** (`AccountingPayment` + document `paid`)
- [ ] Idempotence prouvée par test (replay webhook sans doublon)
- [ ] Signature HMAC fail-closed testée (401 sans secret/signature invalide)
- [ ] OpenAPI : 2 opérations + schémas ; couverture routes 100 % ; SDK régénérés
- [ ] i18n ×4 des messages (échec, expiration, lien de paiement) ; RBAC portail/comptable/principal
- [ ] CI verte ; CHANGELOG

## Implémentation (2026-08-25 — issue #5272)

Implémenté par la PR de clôture #5272 (branche `mod/accounting/5272-online-payment`) :
`PaymentGatewayInterface` + `PaymentGatewayFactory` (routage pays), `ChargilyPaymentGateway`
(API v2) et `StripePaymentGateway` (Checkout Session), `OnlinePaymentService` (checkout +
webhook idempotent), migration `gateway_payment_id` (index UNIQUE), 14 tests Feature
`AccountingOnlinePaymentTest` (US1-3 + idempotence + anti-fraude + isolation tenant),
OpenAPI 2 opérations (804/804), i18n ×4. Les 3 décisions fondateur de l'ADR-0017 restent
ouvertes (elles ne bloquent pas l'option A retenue par défaut).

## Hors périmètre (cette décision d'architecture)

- Choix des passerelles MA/TN/TR (phase 2)
- Paiement par virement manuel (déjà couvert #5365)
- Abonnements SaaS (déjà couverts par Billing Stripe/Chargily)
