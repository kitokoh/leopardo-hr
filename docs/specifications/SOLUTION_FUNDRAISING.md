# SOLUTION — Verticale FUNDRAISING (cagnottes solidaires)

> Statut : **conception validée — backend v1 en cours d'implémentation**
> Branche : `feature/fundraising-vertical`
> Backlog d'implémentation : `docs/specifications/FUNDRAISING_TASKS.md`
> Module serveur : `api/app/Modules/Fundraising/`

---

## 1. Contexte et objectif

Leopardo est un HRMS multi-vertical (Travel, Pharmacy, Hospitality, Retail…).
La verticale **Fundraising** ouvre la plateforme à un usage « social » :
permettre à une organisation (association, ONG, fondation, mosquée/église,
mutuelle, entreprise citoyenne) de **collecter de l'argent pour une personne
ou une cause**, en s'inspirant des solutions éprouvées du marché :

| Solution | Ce qu'on en retient |
|---|---|
| **GoFundMe** | Lien public de cagnotte partageable, contribution en 3 clics, montants libres ou suggérés (50, 100, 200…), message du contributeur, anonymat optionnel. |
| **Leetchi** | Cagnotte avec objectif et date de fin, contribution sans compte, reversement au bénéficiaire en fin de collecte. |
| **HelloAsso** | Pas de commission prélevée sur le don (pourboire optionnel), reçus, transparence du montant collecté. |
| **LaunchGood** | Communauté + mobile money / cartes internationales, causes vérifiées. |
| **KissKissBankBank** | Workflow de validation de la campagne avant publication, règles de reversement strictes. |

**Cas d'usage canonique** : une personne en difficulté (frais médicaux,
scolarité, sinistre, mariage, projet solidaire) est portée par une
organisation qui crée la cagnotte → un **lien public unique** est généré →
les contributeurs donnent chacun 50, 100, 500… via **carte bancaire ou
mobile money** → à l'échéance (ou à l'atteinte de l'objectif), le responsable
demande le **reversement** au bénéficiaire.

### Objectifs v1 (ce lot)

1. Backend API complet et **solide** : domaine DDD, migrations, passerelles
   de paiement derrière un contrat, webhooks signés, idempotence, tests.
2. Zéro dépendance frontend : le front public (page de cagnotte) et les
   écrans de gestion arrivent dans un lot ultérieur — l'API publique est le
   contrat.

### Non-objectifs v1

- Pas de KYC/AML bancaire complet (hook prévu, phase 2).
- Pas de dons récurrents (phase 2).
- Pas de frais de plateforme prélevés sur la collecte (commission = 0 en v1,
  `platform_fee` réservé).
- Pas d'application mobile dédiée (les apps Flutter existantes consommeront
  l'API plus tard).

---

## 2. Acteurs et parcours

| Acteur | Rôle |
|---|---|
| **Responsable tenant** (principal / rh / gestionnaire fundraising) | Crée et gère les cagnottes, confirme les contributions manuelles, demande et suit les reversements. |
| **Contributeur** (anonyme ou identifié, **sans compte**) | Consulte la page publique de la cagnotte, choisit un montant, paie par carte ou mobile money, laisse un message optionnel. |
| **Admin plateforme** | Active la verticale `fundraising` par tenant (feature flag fail-closed). |

**Parcours nominal**

1. Admin plateforme active le flag `fundraising` sur le tenant.
2. Le responsable crée une cagnotte (`draft`) : titre, description,
   bénéficiaire, objectif, échéance, montants suggérés, visuel.
3. Il la **publie** (`active`) → le lien public `/cagnottes/{slug}` devient
   consultable et contributif.
4. Les contributeurs paient : carte (Stripe Checkout) ou mobile money
   (push USSD / agrégateur). La contribution passe `pending → completed`
   via webhook signé (ou vérification active), sinon `failed`.
5. Le compteur `collected_amount` de la cagnotte se met à jour de façon
   transactionnelle (jamais recalculé en lecture).
6. À l'échéance ou à l'objectif atteint, le responsable **clôture** la
   cagnotte puis demande un **reversement** (mobile money ou virement) au
   bénéficiaire : `requested → processing → paid` (ou `failed`/`cancelled`).
7. Audit complet : chaque événement paiement est journalisé
   (`fundraising_payment_events`, idempotence webhook).

---

## 3. Modèle de domaine

### 3.1 `fundraisers` (cagnotte)

| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | uuid | tenant, index (convention §2.6 : sans FK) |
| `slug` | string(160) | **unique global** — lien public |
| `title` | string(190) | |
| `description` | text nullable | |
| `beneficiary_name` | string(190) | personne/cause bénéficiaire |
| `beneficiary_contact` | string(190) nullable | tél/email du bénéficiaire (RGPD : non exposé publiquement) |
| `category` | string(40) | `medical\|education\|emergency\|community\|project\|other` |
| `goal_amount` | decimal(15,2) nullable | objectif ; null = sans objectif |
| `collected_amount` | decimal(15,2) default 0 | dénormalisé, mis à jour en transaction |
| `contributions_count` | unsigned int default 0 | idem |
| `currency` | string(3) | ISO 4217 (XOF, XAF, EUR, DZD…) |
| `suggested_amounts` | json nullable | montants suggérés `[50,100,200]` |
| `min_amount` / `max_amount` | decimal(15,2) nullable | garde-fous contribution |
| `cover_image_path` | string nullable | visuel (phase front) |
| `status` | string(20) | `draft\|active\|paused\|completed\|closed\|cancelled` |
| `starts_at` / `ends_at` | timestamp nullable | fenêtre de collecte |
| `published_at` | timestamp nullable | |
| `created_by` | uuid nullable | employé créateur |
| timestamps | | |

**Transitions de statut**

```
draft ──publish──▶ active ──pause──▶ paused ──resume──▶ active
active ──goal reached / ends_at──▶ completed
active|paused|completed ──close──▶ closed      (collecte arrêtée, reversement possible)
draft|active ──cancel──▶ cancelled (aucun reversement ; remboursements phase 2)
```

Règles :
- Contribution acceptée **uniquement** si `status = active` et dans la
  fenêtre `starts_at..ends_at`.
- `completed` est posé automatiquement quand `goal_amount` est atteint
  (la collecte reste possible jusqu'à `closed` — décision produit façon
  GoFundMe ; configurable plus tard).

### 3.2 `fundraising_contributions`

| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | uuid | tenant (dénormalisé pour scoping/index) |
| `fundraiser_id` | bigint | index |
| `reference` | string(32) | **unique** — référence publique `FC-XXXXXXXX` |
| `amount` | decimal(15,2) | |
| `currency` | string(3) | copie de la cagnotte (cohérence webhook) |
| `payment_method` | string(20) | `card\|mobile_money\|bank_transfer\|cash` |
| `provider` | string(30) | `stripe\|mobile_money\|manual` |
| `provider_reference` | string(190) nullable | id session/opérateur |
| `status` | string(20) | `pending\|completed\|failed\|refunded` |
| `contributor_name` | string(190) nullable | |
| `contributor_email` | string(190) nullable | non exposé publiquement |
| `contributor_phone` | string(40) nullable | requis pour mobile money |
| `is_anonymous` | bool default false | masque le nom sur le mur public |
| `message` | string(500) nullable | mot de soutien |
| `metadata` | json nullable | payload provider utile |
| `paid_at` | timestamp nullable | |
| timestamps | | index `(fundraiser_id, status)`, unique `reference` |

### 3.3 `fundraising_payouts` (reversements)

| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | uuid | |
| `fundraiser_id` | bigint | |
| `reference` | string(32) | unique `FP-XXXXXXXX` |
| `amount` | decimal(15,2) | ≤ solde disponible |
| `currency` | string(3) | |
| `method` | string(20) | `mobile_money\|bank_transfer` |
| `recipient_name` | string(190) | |
| `recipient_account` | string(190) | n° mobile money ou IBAN/RIB |
| `status` | string(20) | `requested\|processing\|paid\|failed\|cancelled` |
| `provider_reference` | string(190) nullable | |
| `failure_reason` | string(500) nullable | |
| `requested_by` | uuid nullable | |
| `processed_by` / `processed_at` | uuid / timestamp nullable | |
| timestamps | | |

Règle métier : la somme des payouts `requested|processing|paid` d'une
cagnotte ne dépasse jamais `collected_amount − refunded` (vérifiée en
transaction avec verrou ligne — `lockForUpdate`).

### 3.4 `fundraising_payment_events`

Journal d'idempotence et d'audit des webhooks :
`(id, company_id nullable, provider, event_id unique, contribution_id
nullable, payload json, processed_at nullable, timestamps)`.
Un `event_id` déjà présent ⇒ webhook acquitté sans retraitement (200).

---

## 4. Paiements — contrat et passerelles

Inspiration directe : ADR-0017 Accounting (`PaymentGatewayInterface`,
fail-closed, dual-PSP) et TRAVEL-407 (`PvitPaymentGateway` sandbox).

### 4.1 Contrat `FundraisingGatewayInterface`

```php
interface FundraisingGatewayInterface
{
    public function gatewayName(): string;          // stripe | mobile_money | manual
    public function isConfigured(): bool;           // fail-closed

    /** Initie le paiement d'une contribution `pending`. */
    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation;
    //  → { provider_reference, redirect_url|null, ussd_code|null, status }

    /** Vérifie la signature du webhook (fail-closed → null). */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array;

    /** Extrait l'événement de paiement d'un payload vérifié. */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate;
    //  → { event_id, provider_reference, status: paid|failed, paid_at }

    /** Vérification active (re-conciliation mobile money). */
    public function verify(string $providerReference): ?GatewayPaymentUpdate;
}
```

### 4.2 Drivers v1

| Driver | Méthodes | Comportement |
|---|---|---|
| `StripeContributionGateway` | `card` | Checkout Session (mode sandbox si clé absente → refuse, fail-closed). Webhook `checkout.session.completed` / `payment_intent.payment_failed`, signature HMAC `stripe-signature`. |
| `MobileMoneyGateway` | `mobile_money` | Agrégateur **config-driven** (`fundraising.mobile_money.*`) : opérateurs Orange Money, MTN MoMo, Wave, Moov selon pays. **Mode sandbox** (défaut, pattern PVIT) : `initiate()` retourne une référence `MM-*` + code USSD simulé, `verify()` confirme. Adaptateur production = mêmes entrées, branché sur l'agrégateur choisi (CinetPay/PayDunya/PVIT — TODO documenté, aucune clé en dur). |
| `ManualGateway` | `cash`, `bank_transfer` | Contribution `pending` enregistrée, **confirmée par le responsable** (recu espèces / virement constaté). Pas de webhook. |

Sélection : `FundraisingGatewayFactory::forMethod(payment_method)` → driver ;
`isConfigured() === false` ⇒ `DomainException PAYMENT_GATEWAY_NOT_CONFIGURED`
(fail-closed, jamais de fallback silencieux).

### 4.3 Anti-double-comptabilisation

- Webhook : idempotence par `(provider, event_id)` (table 3.4).
- Mise à jour du compteur : `UPDATE fundraisers SET collected_amount =
  collected_amount + ? …` **dans la même transaction** que le passage
  `pending → completed`, protégée par `lockForUpdate()` ; une contribution
  déjà `completed` n'est jamais re-créditée.
- Vérification active (`verify`) pour le mobile money si le webhook tarde.

---

## 5. API (préfixe `/api/v1`)

### 5.1 Publique (sans compte) — groupe `throttle:shop-public`

| Verbe | Route | Description |
|---|---|---|
| GET | `/public/fundraisers/{slug}` | Fiche publique (DTO dédié : pas d'emails, pas de beneficiary_contact, montants, mur des soutiens non anonymisés côté serveur) |
| GET | `/public/fundraisers/{slug}/supporters` | Mur des contributions `completed` (nom ou « Anonyme », montant masqué si `is_anonymous`, message) |
| POST | `/public/fundraisers/{slug}/contribute` | Initie une contribution (montant, méthode, coordonnées minimales) → `redirect_url` (Stripe) ou `ussd_code`/instructions (mobile money) ou `pending` (manuel) |
| GET | `/public/contributions/{reference}` | Statut d'une contribution par sa référence (polling après USSD) |
| POST | `/webhooks/fundraising/{provider}` | Webhooks providers (signature vérifiée, idempotent) — **hors auth et hors throttle public** |

### 5.2 Privée (gestion tenant) — `auth:sanctum, tenant, module.fundraising, api.manager:principal,rh`

| Verbe | Route | Description |
|---|---|---|
| GET | `/fundraising/fundraisers` | Liste paginée + filtres statut |
| POST | `/fundraising/fundraisers` | Création (`draft`, slug généré unique) |
| GET | `/fundraising/fundraisers/{id}` | Détail + stats |
| PUT | `/fundraising/fundraisers/{id}` | Édition (tant que non `closed/cancelled`) |
| POST | `/fundraising/fundraisers/{id}/publish` | `draft|paused → active` |
| POST | `/fundraising/fundraisers/{id}/pause` | `active → paused` |
| POST | `/fundraising/fundraisers/{id}/close` | `active|paused|completed → closed` |
| GET | `/fundraising/fundraisers/{id}/contributions` | Contributions paginées (tous statuts) |
| POST | `/fundraising/contributions/{id}/confirm` | Confirme une contribution **manuelle** `pending` |
| GET | `/fundraising/fundraisers/{id}/payouts` | Reversements |
| POST | `/fundraising/fundraisers/{id}/payouts` | Demande de reversement (règle solde §3.3) |
| POST | `/fundraising/payouts/{id}/process` | `requested → processing` |
| POST | `/fundraising/payouts/{id}/mark-paid` | `processing → paid` (+provider_reference) |
| POST | `/fundraising/payouts/{id}/fail` | `processing → failed` (+raison) |

Codes d'erreur stables via `DomainException` (leçon #8247 : jamais
`abort(404, 'CODE')`) : `FUNDRAISER_NOT_FOUND`, `FUNDRAISER_NOT_ACTIVE`,
`CONTRIBUTION_NOT_FOUND`, `INVALID_CONTRIBUTION_AMOUNT`,
`PAYMENT_GATEWAY_NOT_CONFIGURED`, `PAYOUT_AMOUNT_EXCEEDS_BALANCE`,
`INVALID_STATUS_TRANSITION`, `WEBHOOK_SIGNATURE_INVALID`.

---

## 6. Sécurité, conformité, exploitation

- **Feature flag fail-closed** : `fundraising` absent du tenant ⇒ 403
  `FEATURE_NOT_ENABLED` (middleware `module.fundraising`). Enregistrement aux
  **3 points obligatoires** : `config/feature-flags.php`,
  `Company::KNOWN_MODULES`, catalogue/roadmap produit.
- **Publique isolée** : les routes publiques n'emportent ni auth ni tenant ;
  une cagnotte `closed` reste lisible mais ne collecte plus ; une cagnotte
  `draft/paused/cancelled` ⇒ 404 publique.
- **RGPD** : `contributor_email/phone` et `beneficiary_contact` jamais
  exposés publiquement ; DTO public dédié ; purge/anonymisation phase 2.
- **Anti-abus** : `min/max_amount` par cagnotte, throttle `shop-public`,
  honeypot `website` côté contribute (champ leurre), pas d'énumération
  (slug non séquentiel, `reference` aléatoire).
- **Montants** : `decimal(15,2)`, devise ISO par cagnotte ; aucune
  conversion v1.
- **Audit** : trait `Auditable` sur les modèles de gestion ; journal
  `fundraising_payment_events` pour toute entrée d'argent.
- **Config** : `config/fundraising.php` (clés Stripe, agrégateur mobile
  money, mode sandbox) — **aucun secret en dur**, `.env.example` documenté.

---

## 7. Architecture du module (DDD, conventions maison)

```
api/app/Modules/Fundraising/
├── Application/
│   ├── Actions/         CreateFundraiser, UpdateFundraiser, PublishFundraiser,
│   │                    PauseFundraiser, CloseFundraiser, InitiateContribution,
│   │                    ConfirmManualContribution, ApplyPaymentUpdate,
│   │                    RequestPayout, TransitionPayout
│   └── DTOs/            GatewayPaymentInitiation, GatewayPaymentUpdate,
│                        PublicFundraiserData
├── Domain/
│   ├── Contracts/       FundraisingGatewayInterface
│   ├── Enums/           FundraiserStatus, ContributionStatus, ContributionMethod,
│   │                    PayoutStatus, PayoutMethod, FundraisingCategory
│   ├── Exceptions/      FundraisingException (codes stables)
│   ├── Models/          Fundraiser, FundraisingContribution, FundraisingPayout,
│   │                    FundraisingPaymentEvent
│   ├── Policies/        FundraiserPolicy, FundraisingPayoutPolicy
│   └── Support/         FundraisingFeatures, SlugGenerator, ReferenceGenerator
├── Infrastructure/
│   └── Services/        FundraisingGatewayFactory, StripeContributionGateway,
│                        MobileMoneyGateway, ManualGateway
├── Interfaces/Api/V1/
│   ├── Controllers/     FundraiserController, FundraiserPublicController,
│   │                    FundraisingWebhookController, FundraisingPayoutController
│   ├── Requests/        StoreFundraiserRequest, UpdateFundraiserRequest,
│   │                    InitiateContributionRequest, RequestPayoutRequest
│   └── Resources/       FundraiserResource, FundraiserPublicResource,
│                        ContributionResource, PayoutResource
└── Providers/           FundraisingServiceProvider
```

Migrations tenant : `database/migrations/tenant/2026_10_10_0001xx_create_fundraising_*`
(idempotentes `schemaTableExists`, `down()` qui ne détruit que ce que le
`up()` du même fichier crée — leçon #8207).

Routes : `api/routes/modules/fundraising.php`, requis depuis `routes/api.php`
dans le groupe `/v1` (jamais de re-préfixe).

Enregistrements : `bootstrap/providers.php`, alias middleware dans
`bootstrap/app.php`, policies dans `App\Providers\AuthServiceProvider`
(règle PA2-ARCH-008), `config/feature-flags.php`, `Company::KNOWN_MODULES`,
`ARCHITECTURE.md` (32 → 33 modules), `CHANGELOG.md` `[Unreleased]`.

---

## 8. Plan de tests

- **Feature** (`tests/Feature/Fundraising/`) :
  - domaine : migrations idempotentes, unicité slug/références, scope tenant,
    transitions de statut, feature flag deny-by-default, policy ;
  - flux public : publication → contribute sandbox mobile money → polling →
    compteur à jour ; contribute carte non configurée ⇒ 503 fail-closed ;
    cagnotte `draft` ⇒ 404 publique ; honeypot ;
  - reversement : règle de solde (dépassement refusé), workflow
    `requested → processing → paid` ;
  - webhook : signature invalide rejetée, idempotence `event_id`,
    double livraison sans double crédit.
- **Unit** (`tests/Unit/Fundraising/`) : `SlugGenerator`,
  `ReferenceGenerator`, garde-fous montants, factory de gateway.

## 9. Roadmap

| Phase | Contenu |
|---|---|
| **v1 (ce lot)** | Backend complet : domaine, API publique/privée, sandbox Stripe + mobile money, reversements manuels, tests. |
| v1.1 | Front public Next.js (`front/web` ou app dédiée) : page cagnotte, partage OG, QR du lien. |
| v1.2 | Adaptateurs production mobile money (CinetPay/PayDunya/PVIT selon pays) + re-conciliation planifiée. |
| v1.3 | KYC bénéficiaire, reversements automatisés via provider, remboursements, dons récurrents, reçus PDF. |
| v2 | App Flutter `leopardo_fundraising`, notifications temps réel, cagnottes multi-bénéficiaires, frais de plateforme optionnels. |
