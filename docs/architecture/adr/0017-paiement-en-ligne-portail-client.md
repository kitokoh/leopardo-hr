# ADR 0017 — Paiement en ligne des factures (portail client) : passerelle(s) et architecture

## Statut

**Proposée** — issue #5272, décision fondateur requise (choix de la passerelle pilote).

**Date** : 2026-08-24

## Contexte

Le portail client sécurisé (#5357, en cours) permet d'envoyer des documents (factures, avoirs,
proformas) par email avec un lien de consultation. L'issue #5272 demande que le client puisse
**régler sa facture en ligne** avec **rapprochement automatique** dans `AccountingPayment`
(module Accounting, socle #5221) — DoD : « un paiement en ligne pilote rapproché sans
intervention ».

Le repo dispose déjà de briques exploitables :

| Brique | Où | État |
|---|---|---|
| `StripeService` (Checkout Sessions + Customer Portal, REST sans SDK) | `api/app/Modules/Billing/Infrastructure/Services/` | existant (abonnements) |
| `ChargilyService` (webhook HMAC-SHA256, **fail-closed** si secret absent) | idem | existant (abonnements DZ) |
| `StripeWebhookController` / `PaymentWebhookController` | `api/app/Modules/Billing/Interfaces/Api/V1/` | existant |
| `AccountingPayment` + `PaymentMethod` enum | `api/app/Modules/Accounting/Domain/` | socle #5221 |
| `AccountingSettings` (devise entreprise) | module Accounting | mergé (#5232) |
| Workflow documents (draft → sent → partially_paid → paid, règles de transition) | module Accounting | mergé (#5223) |
| `IdempotencyMiddleware` (`Idempotency-Key`) | `api/app/Core/Http/Middleware/` | mergé (#5277) |
| Portail client (token + expiration + PDF) | module Accounting | PR #5357 en cours |

Le paiement en ligne des **documents** est donc un ajout sur des briques existantes — il ne
réinvente ni le stockage des paiements, ni la vérification de webhook, ni l'idempotence.

## Disponibilité des passerelles (vérifié 2026-08-24)

### Stripe — liste officielle (stripe.com/global, consulté le 2026-08-24)

Supporté : France, Royaume-Uni, États-Unis, **Côte d'Ivoire (Extended network)**, Ghana,
Kenya, Nigeria, Afrique du Sud (Extended network), etc.
**Non supporté** : Algérie, Maroc, Tunisie, Turquie, Sénégal, Cameroun.

→ Stripe couvre les clients FR/EN/US et CI, **pas le pilote DZ**.

### Chargily (Algérie) — tarifs officiels (chargily.com/dz/business/pay/pricing, 2026)

| Plan | Commission | Conditions |
|---|---|---|
| Startup | **0 %** | ≤ 300 000 DZD/mois, ≤ 300 tx/mois, 1 payout/mois offert |
| Comfort | **1,25 %** (min 12,5 DZD, max 1 250 DZD) | illimité, 2 payouts/mois offerts |
| Supreme | **2,5 %** (min 25 DZD, max 2 500 DZD) | illimité, payouts illimités |

- Payouts au-delà du quota : 1,5 % (min 150 DZD). Zéro abonnement, API gratuite, plugins open-source.
- Moyens : **CIB (SATIM) + EDAHABIA (Algérie Poste)** ; Visa/Mastercard « coming soon ».

→ Unique passerelle locale mature pour le pilote DZ. Intégration Laravel existante dans le repo
(webhook) + SDK/plugins officiels.

### Maroc / Tunisie (hors pilote, phase suivante)

- **MA** : CMI (historique, sponsoring banque requis, ~3,5 % + frais fixes, délais ~15 j selon
  comparatifs 2026), PayZone (inscription directe en ligne), Chari Pay (cartes + Maroc Pay, API REST).
- **TN** : PSP locaux (e-Dinar, etc.) — à étudier en phase 2.
- **TR** : Stripe non disponible (2026) — PSP locaux (iyzico, PayTR, Param…) en phase 2.

### PayPal (Algérie) — écarté

Comptes possibles mais **réception de paiements business non fiable** (restrictions
réglementaires, erreurs « this payment can't be completed due to regulations » sur les
réceptions) → inutilisable comme passerelle d'encaissement pour DZ.

## Options envisagées

### Option A — Dual-PSP piloté par pays entreprise (recommandée)

`PaymentGatewayInterface` commune ; routage par pays de l'entreprise :

- **DZ → Chargily** (plan Comfort 1,25 %) — pilote.
- **FR/UK/US/CI → Stripe** (Checkout Session, pattern existant `StripeService`).
- MA/TN/TR/SN/CM → passerelles locales en phase 2 (la même interface les accueille).

**Pour** : couvre le pilote DZ ET les marchés internationaux ; réutilise les deux services
existants ; une seule surface d'intégration pour l'UI/les webhooks (par passerelle).
**Contre** : deux contrats/frais à gérer ; deux flux de webhook (mais pattern HMAC identique).

### Option B — Stripe uniquement

**Pour** : une seule passerelle, intégration éprouvée.
**Contre** : **aucun moyen de paiement local en Algérie** → le DoD pilote DZ est
**infaisable** ; conversion/onboarding des clients DZ impossible. Écartée.

### Option C — Agrégateur multi-pays unique (ex. Dodo Payments, Paymento…)

**Pour** : un contrat unique, onboarding simplifié.
**Contre** : dépendance à un acteur intermédiaire (frais + marges, maturité réglementaire à
vérifier, less-known en DZ) ; n'apporte rien pour le pilote DZ par rapport à Chargily en direct.
À réévaluer quand MA/TN/TR seront cibles.

## Décision recommandée

**Option A** : Chargily (DZ, pilote) + Stripe (FR/UK/US/CI), derrière une
`PaymentGatewayInterface` commune, routée par la devise/pays de `AccountingSettings`.

- Le **pilote DZ** (DoD #5272) passe par **Chargily plan Comfort (1,25 %)** — 2 payouts/mois
  offerts, illimité en volume : adapté à une PME qui émet quelques factures.
- L'**international** (FR/EN/US/CI) passe par Stripe (Checkout Session, pattern existant).
- MA/TN/TR : ajout de passerelles locales (CMI/PayZone, e-Dinar, iyzico…) en phase 2 — même
  interface, coût d'ajout faible.

## Architecture d'intégration (cible)

```
Portail client (#5357) ou comptable
        │ POST /accounting/documents/{id}/checkout   (RBAC portail/comptable/principal)
        ▼
PaymentGatewayFactory (routing par devise/pays entreprise)
        ├── ChargilyGateway  (DZ)  : Payment Link/Checkout API → URL de paiement
        └── StripeGateway    (FR/UK/US/CI) : Checkout Session (pattern StripeService)
        ▼
Retour client → passerelle → webhook
        ▼
POST /accounting/payments/webhook/{gateway}   (signature HMAC, fail-closed — pattern ChargilyService)
        ▼
Événement paiement (idempotent : Idempotency-Key #5277 + UNIQUE gateway_payment_id)
        ▼
RecordPayment → AccountingPayment (method=online_chargily|online_stripe, gateway_payment_id,
montant TTC, devise) → AccountingDocument.status (paid | partially_paid — règles #5223)
```

Points de sûreté :

1. **Signature webhook vérifiée** avant tout traitement (HMAC par passerelle, fail-closed si
   secret absent — pattern existant `ChargilyService`, #2615).
2. **Idempotence** : le replay d'un webhook (retries passerelle) est absorbé par
   `Idempotency-Key` (#5277) et par l'unicité `gateway_payment_id` — jamais de double paiement.
3. **Montant et devise vérifiés** : le payload webhook doit matcher le TTC du document
   (anti-fraude montant≠) ; un écart → refus + alerte.
4. **Isolation tenant** : le paiement est rattaché au document (déjà scopé `BelongsToCompany`) ;
   jamais de rapprochement cross-tenant.
5. **Échec/expiration** : le document reste `sent` ; la relance suit les règles #5365.

## Conséquences

- Nouveau dossier d'implémentation dans le module Accounting (`PaymentGatewayFactory`,
  `ChargilyGateway`, `StripeGateway`, contrôleurs webhook/checkout) — **après décision
  fondateur** (cette PR ne contient que la décision d'architecture).
- Config `config/services.php` : clés API/webhooks Chargily + Stripe par environnement
  (patterns existants) ; jamais commitées.
- OpenAPI : 2 opérations nouvelles (checkout, webhook) + schémas — régénérés à
  l'implémentation.
- RGPD : données de paiement minimisées (pas de PAN stocké — la passerelle héberge tout).

## Décision requise (fondateur)

1. **Valider l'Option A** (Chargily DZ + Stripe international, interface commune) ?
2. Valider le **plan Comfort Chargily (1,25 %)** pour le pilote ?
3. Valider le périmètre pilote : **une entreprise DZ émet une facture → le client paie en
   ligne via CIB/Edahabia → rapprochement automatique sans intervention** ?

*Réponse attendue sur l'issue #5272 — l'implémentation démarre dès validation.*
