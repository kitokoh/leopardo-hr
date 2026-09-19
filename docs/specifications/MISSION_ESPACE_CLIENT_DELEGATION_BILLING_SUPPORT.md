# Spécification — Espace client : délégation d'accès, facturation, support, crédits IA

> **Statut** : validée par le fondateur (mandat direct, session du 2026-09-19 — carte blanche
> donnée à l'agent pour spécifier, ouvrir les issues, implémenter et merger).
> **Périmètre freeze 60j** : billing/checkout = ✅ autorisé (funnel) ; tickets support = rattaché
> au SLA pilotes ; crédits IA = exception de scope **décidée par le fondateur** (issue marquée
> `[FREEZE-EXCEPTION]`).
> **BC concernés** : BC-01 PLATFORM, BC-02 TENANT, BC-21 BILLING, BC-23 AI.

## 1. Contexte

Leopardo a deux espaces web : `front/admin-dashboard` (super-admin plateforme) et `front/web`
(portail unique du client/tenant — fondateur de PME et ses collaborateurs). Le fondateur veut
garantir quatre capacités côté client :

1. **Délégation d'accès** : le responsable du tenant (principal) invite des collaborateurs et
   leur délègue l'accès à des modules précis (marketing, comptabilité, tickets…), de façon
   **composable** (plusieurs modules par collaborateur). Le collaborateur reçoit un email
   d'invitation et accède uniquement à son périmètre.
2. **Facturation** : le client dispose d'un espace où payer ses factures d'abonnement Leopardo
   et **recevoir** sa facture (email + PDF), y compris en mode sandbox tant que les identifiants
   bancaires réels ne sont pas posés.
3. **Tickets support** : le client ouvre des tickets depuis son espace ; l'administration
   plateforme les reçoit et y répond ; la gestion des tickets est **délégable** à un
   collaborateur comme n'importe quel module.
4. **Crédits IA** : à côté de l'abonnement, achat **facultatif** de tokens IA, visible
   **uniquement** dans l'espace Facturation.

## 2. État des lieux (audit du 2026-09-19, main @ 953a685)

| Capacité | Existant | Manques |
|---|---|---|
| Invitations collaborateurs | `user_invitations`, `UserInvitationService`, mail d'activation par lien (choix du mot de passe par l'invité — plus sûr que des identifiants en clair), UI `settings/team` (#7555) | pas de révocation d'invitation ; rôles proposés limités |
| Accès par module | `api.manager:<roles>` (enum `manager_role`), `hasRoleAccess()` front | 1 seul rôle par collaborateur, pas de composition multi-modules ; front autorise par défaut hors modules listés ; rien pour « support » |
| Billing | Module `Billing` complet (plans, souscription, factures PDF, checkout/portal Stripe, webhooks idempotents) | aucun email de facture/reçu ; `stripe_invoice_id` jamais rapproché ; prix dupliqués (env / plans admin / `GenerateMonthlyInvoices`) |
| Tickets | API tenant complète (`/support-tickets`), UI admin plateforme (`SupportTicketsView.vue`), permission plateforme `support.manage` | **aucune UI tenant** dans front/web (i18n déjà prête) ; zéro notification email ; pas de délégation tenant |
| Crédits IA | Budgets techniques (`TokenBudgetGuard`), quotas en cache **volatils** avec plan codé en dur `'starter'` | tout est à créer : ledger, achat one-shot Stripe, décompte persistant, UI billing |

## 3. Cible

### 3.1 Délégation par module (BC-02 TENANT)

- Nouvelle table **tenant** `employee_module_grants` (`company_id` obligatoire — preset
  multitenancy) : `employee_id`, `module_key`, `granted_by_employee_id`, timestamps ;
  unique (`company_id`,`employee_id`,`module_key`).
- `module_key` ∈ registre fermé : `marketing`, `accounting`, `support`, `crm`, `showcase`,
  `hr`, `billing_view` (extensible par constante, pas d'entrée libre).
- API (principal uniquement) : `GET/PUT /api/v1/employees/{id}/module-grants`.
- Enforcement : le middleware d'accès module accepte `manager_role` historique **ou** grant
  explicite. Le `principal` a implicitement tout.
- Front `settings/team` : composition des modules par collaborateur (cases à cocher),
  libellés FR/EN/AR/TR. `hasRoleAccess()` passe en **refus par défaut** + prise en compte
  des grants (le menu ne montre que le périmètre accordé).
- Invitations : ajout `DELETE /invitations/{id}` (révocation) + bouton UI.
- L'email d'invitation existant (lien d'activation) reste le canal de remise des accès.

### 3.2 Tickets côté client + notifications (BC-01 PLATFORM)

- Page `front/web` `(dashboard)/support` : liste (statut, catégorie, priorité), création,
  fil de messages, réponse, clôture — consomme l'API existante `/api/v1/support-tickets`,
  réutilise les clés i18n `support_tickets` déjà présentes.
- Accès : visible pour `principal` + collaborateurs ayant le grant `support` (cf. 3.1).
- Emails : nouveau ticket → équipe plateforme (super-admins avec `support.manage`) ;
  réponse plateforme → auteur du ticket ; réponse client → admin assigné (ou équipe).
  Mailables i18n via `EmailTemplateResolver`, envoi en queue.
- Entrée de menu + badge de statut dans le layout dashboard.

### 3.3 Facturation aboutie (BC-21 BILLING)

- **Email de facture** à l'émission (`GenerateMonthlyInvoices`) et **reçu** au passage à
  `paid` (webhook) — PDF joint, i18n.
- **Rapprochement Stripe** : écrire `stripe_invoice_id` à la création/paiement Stripe et
  faire transiter la facture interne vers `paid` sur `invoice.paid`.
- Source de prix unique : `GenerateMonthlyInvoices` lit les plans admin (table `plans`),
  suppression de `PLAN_PRICES` codé en dur.
- Sandbox : le flux complet (checkout simulé → facture → email) reste jouable avec
  `SANDBOX_CHECKOUT=true` sans identifiants bancaires réels.

### 3.4 Crédits IA (BC-23 AI / BC-21 BILLING) — `[FREEZE-EXCEPTION]` décidée fondateur

- Tables tenant : `ai_credit_ledger` (achats +, consommation −, `company_id`, idempotence
  webhook) ; solde = somme du ledger (ou table de solde matérialisée).
- Packs de tokens (constantes versionnées) ; achat via Stripe Checkout `mode=payment`
  one-shot ; webhook `checkout.session.completed` (mode payment) crédite le ledger,
  idempotent via `WebhookEventRegistry`.
- Consommation : `AIRateLimiter` dérive le plan réel via `EntitlementGuard`
  (fin du `'starter'` codé en dur), quota du plan d'abord, puis débit des crédits achetés ;
  compteur persistant en DB, plus de cache volatil. Fail-closed si solde nul.
- UI : section « Crédits IA » **uniquement** dans `(dashboard)/billing` — solde, packs,
  historique. Facultative : aucun blocage d'abonnement si jamais achetée.

## 4. Hors périmètre

- Config PSP par l'admin + profils de paiement tenant (lot BC-21 « deep maturity », backlog).
- Rôles personnalisés définissables par le tenant (matrice permission×module libre).
- Pièces jointes dans les tickets ; temps réel (broadcast `support.ticket.created`).
- Envoi de mots de passe en clair par email (refusé : le lien d'activation est conservé).

## 5. Critères d'acceptation globaux

- Chaque lot passe les 4 checks requis (PHPStan Strict, Module Structure Validator,
  Frontend ESLint+TS, actionlint) + gardes migrations (collisions basename, zéro
  `Schema::create` dupliqué).
- Toute nouvelle table tenant porte `company_id` ; aucune fuite cross-tenant (tests).
- i18n fr/en/ar/tr pour toute chaîne UI/email ; entrées `CHANGELOG.md` par PR.
- Issues fermées par `Closes #N` dans le body des PRs de lot (PA2-OPS-008).
