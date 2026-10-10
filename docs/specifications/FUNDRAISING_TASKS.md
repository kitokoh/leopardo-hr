# FUNDRAISING — Backlog d'implémentation (verticale cagnottes solidaires)

> Spec de référence : `docs/specifications/SOLUTION_FUNDRAISING.md`
> Branche : `feature/fundraising-vertical`
> Convention de statut : ✅ fait · 🚧 en cours · ✅ à faire
> Découpage : chaque tâche = un commit poussé sur la branche.
> État au 2026-10-10 : lots A→G livrés et poussés (9 commits). Phase 2 hors périmètre.

## Lot A — Conception

| ID | Tâche | Statut |
|---|---|---|
| FUND-001 | Conception détaillée (business case, domaine, paiements, sécurité, API) + backlog | ✅ |

## Lot B — Socle domaine

| ID | Tâche | Statut |
|---|---|---|
| FUND-010 | Migration tenant `fundraisers` (idempotente, down() propre) | ✅ |
| FUND-011 | Migration tenant `fundraising_contributions` | ✅ |
| FUND-012 | Migration tenant `fundraising_payouts` | ✅ |
| FUND-013 | Migration tenant `fundraising_payment_events` (idempotence webhook) | ✅ |
| FUND-014 | Enums Domain (FundraiserStatus, ContributionStatus, ContributionMethod, PayoutStatus, PayoutMethod, FundraisingCategory) | ✅ |
| FUND-015 | Modèles Domain (Fundraiser, FundraisingContribution, FundraisingPayout, FundraisingPaymentEvent) + trait tenant | ✅ |
| FUND-016 | Support : FundraisingFeatures, SlugGenerator, ReferenceGenerator | ✅ |
| FUND-017 | FundraisingException + codes d'erreur stables | ✅ |

## Lot C — Paiements

| ID | Tâche | Statut |
|---|---|---|
| FUND-020 | Contrat `FundraisingGatewayInterface` + DTOs (GatewayPaymentInitiation, GatewayPaymentUpdate) | ✅ |
| FUND-021 | `StripeContributionGateway` (carte, checkout + webhook HMAC, fail-closed) | ✅ |
| FUND-022 | `MobileMoneyGateway` (agrégateur config-driven, sandbox USSD/push — pattern PVIT) | ✅ |
| FUND-023 | `ManualGateway` (espèces/virement confirmé par le responsable) | ✅ |
| FUND-024 | `FundraisingGatewayFactory` + `config/fundraising.php` + `.env.example` | ✅ |

## Lot D — Application

| ID | Tâche | Statut |
|---|---|---|
| FUND-030 | Actions cagnotte : Create, Update, Publish, Pause, Close | ✅ |
| FUND-031 | `InitiateContribution` (public) — garde-fous montant, fenêtre, honeypot | ✅ |
| FUND-032 | `ApplyPaymentUpdate` — transaction, lockForUpdate, compteur, auto-complete | ✅ |
| FUND-033 | `ConfirmManualContribution` (responsable) | ✅ |
| FUND-034 | Actions reversement : RequestPayout (règle solde), TransitionPayout | ✅ |

## Lot E — API

| ID | Tâche | Statut |
|---|---|---|
| FUND-040 | Controllers privés : FundraiserController, FundraisingPayoutController (+ confirm contribution) | ✅ |
| FUND-041 | Controllers publics : FundraiserPublicController, FundraisingWebhookController | ✅ |
| FUND-042 | Requests (Store/Update Fundraiser, InitiateContribution, RequestPayout) + Resources (privé/public) | ✅ |
| FUND-043 | `routes/modules/fundraising.php` + require dans `routes/api.php` | ✅ |

## Lot F — Enregistrement

| ID | Tâche | Statut |
|---|---|---|
| FUND-050 | `FundraisingServiceProvider` + `bootstrap/providers.php` | ✅ |
| FUND-051 | Middleware `module.fundraising` (fail-closed) + alias `bootstrap/app.php` | ✅ |
| FUND-052 | Policies (Fundraiser, Payout) enregistrées dans `AuthServiceProvider` | ✅ |
| FUND-053 | Feature flag `fundraising` : `config/feature-flags.php` + `Company::KNOWN_MODULES` (3 points d'enregistrement) | ✅ |

## Lot G — Qualité

| ID | Tâche | Statut |
|---|---|---|
| FUND-060 | Tests Feature : domaine + flux public + reversements + webhooks | ✅ |
| FUND-061 | Tests Unit : générateurs, factory, garde-fous | ✅ |
| FUND-062 | `CHANGELOG.md` [Unreleased] + `ARCHITECTURE.md` (33 modules) | ✅ |

## Phase 2 (hors ce lot)

| ID | Tâche | Statut |
|---|---|---|
| FUND-100 | Front public (page cagnotte, partage OG, QR) | ⬜ |
| FUND-101 | Adaptateurs production mobile money (CinetPay/PayDunya/PVIT) | ⬜ |
| FUND-102 | openapi.yaml + SDK dev-hub | ⬜ |
| FUND-103 | KYC bénéficiaire, remboursements, dons récurrents, reçus PDF | ⬜ |
