# Feature Specification: Module Comptabilité — Paiements + rapprochement + relances (Closes #5229)

**Feature Branch**: `mod/accounting/5229-payments`
**Issue**: #5229 (P1, backend, billing — Phase B)
**Created**: 2026-08-23
**Status**: Implementation

## Contexte

Le socle data (#5221) fournit `accounting_payments` (montant, méthode cash/bank_transfer/check/card/other,
référence chiffrée, `received_at`, `reconciled_at`, statut pending/recorded/matched) et `accounting_documents`
(`paid_amount`, statuts draft/sent/partially_paid/paid/…). Le journal (#5234) consomme déjà les paiements
`recorded`/`matched`. Cette issue livre la **Phase B trésorerie** : enregistrement de paiements avec la règle
**jamais payé > total**, rapprochement manuel, statuts de document `partially_paid`/`paid`, et **relances
automatiques** (J+7/J+15/J+30 paramétrables) via le module Notification.

**Décisions de cadrage** :
- L'enregistrement d'un paiement met à jour `paid_amount` + statut du document de façon **minimale et locale**
  (draft→…→paid complet porté par #5223/PR #5346, non mergée) — règle : `paid_amount >= total_ttc ⇒ paid`,
  sinon `partially_paid` (uniquement si le document était `sent`/`partially_paid`/`overdue`).
- Rapprochement : `pending → recorded` (enregistrement) puis `recorded → matched` (rapprochement manuel,
  horodaté `reconciled_at`).
- Relances : commande/API explicite (pas de scheduler automatique pour l'instant — la CI ne peut pas l'attendre),
  idempotente par unique `(document_id, stage)`. Stages par défaut [7, 15, 30] jours, configurables par entreprise
  (`accounting_settings.payment_reminder_days`).
- Destinataires des relances : managers `principal` + `comptable` du tenant (CommunicationService, canal app par
  défaut + préférences respectées).
- Aucune modification du moteur de paie, aucun conflit avec #5222/#5223/#5225/#5234 (chemins API dédiés
  `accounting/payments`, `accounting/reminders`).

## User Stories

### US1 — Enregistrer un paiement sans jamais dépasser le total (P1)

**Independent Test**: test Feature — paiement partiel → `paid_amount` incrémenté + statut `partially_paid` ;
paiement complet → `paid` ; paiement excédentaire → exception `PaymentExceedsTotalException` (422), rien n'est écrit.

**Scenarios**:
1. **Given** une facture `sent` TTC 1190, **When** paiement 500 (bank_transfer), **Then** `paid_amount=500`, statut `partially_paid`.
2. **Given** un paiement complémentaire 690, **When** enregistré, **Then** `paid_amount=1190`, statut `paid`.
3. **Given** un paiement 1191 sur la facture, **When** enregistré, **Then** 422 `PAYMENT_EXCEEDS_TOTAL`, aucun paiement créé.
4. **Given** un paiement sur document brouillon, **When** enregistré, **Then** 422 `PAYMENT_ON_UNSENT_DOCUMENT`.

### US2 — Rapprocher un paiement (P1)

**Independent Test**: `register()` crée le paiement en `recorded` (enregistré) ; `reconcile()` → `matched` + `reconciled_at` posé ;
reconcilier deux fois est sans effet (idempotent).

### US3 — Lister les paiements (P1)

**Independent Test**: `GET /accounting/payments?document_id=&status=` filtre par document et statut, RBAC comptable/principal (403 sinon).

### US4 — Relances sans doublon (P2)

**Independent Test**: commande/API `accounting:send-payment-reminders` sur une facture échue J+7 → 1 relance (row
`accounting_payment_reminders` + notification) ; deuxième exécution → 0 nouvelle relance (unique document+stage) ;
facture payée → aucune relance ; `payment_reminder_days` personnalisé respecté.

## Requirements

- **FR-001**: migration tenant additive `2026_08_23_000004_...` :
  - `accounting_payment_reminders` : `company_id` (non nullable), `document_id` (bigint), `stage` (int),
    `sent_at` (timestamp), unique `(company_id, document_id, stage)` — garantit « relances sans doublon ».
  - `accounting_settings.payment_reminder_days` (json, nullable → défaut [7, 15, 30]).
- **FR-002**: `PaymentRegistrationService` (Infrastructure/Services) : `register()`, `reconcile()`, `list()` —
  règles US1/US2, transactions, exceptions domaine `PaymentExceedsTotalException` (422), `PaymentOnUnsentDocumentException` (422).
- **FR-003**: `PaymentReminderService` : `days()`, `run()` — cibles factures/avoirs échus (due_date < now) non soldés,
  stages configurés, unique par stage, notification via `CommunicationService::notifyEmployee` (template
  `accounting_payment_reminder`, catégorie `accounting`, title/body i18n ×4), échec notification ≠ échec relance (log).
- **FR-004**: commande artisan `accounting:send-payment-reminders`.
- **FR-005**: `AccountingPaymentController` (RBAC `api.manager:principal,comptable`) :
  - `POST /api/v1/accounting/documents/{document}/payments` (register)
  - `POST /api/v1/accounting/payments/{payment}/reconcile`
  - `GET /api/v1/accounting/payments?document_id=&status=`
  - `POST /api/v1/accounting/reminders/run` → `{ reminders_sent }`
- **FR-006**: OpenAPI (4 paths) + miroir régénéré ; note scénarios ; CHANGELOG root + api.
- **FR-007**: tests Feature `AccountingPaymentTest` (US1→US4, RBAC, isolation tenant) — 12+ tests.

## Hors périmètre

- Workflow complet de documents (transition draft→sent→paid) — porté par #5223 (PR #5346).
- Paiement en ligne portail client (#5272), multi-devises (#5270), TVA multi-pays (#5271).
- Pas de scheduler cron (run explicite commande/API — reproductible en CI).

## Success Criteria

- Règle « jamais payé > total » vérifiée (unitaire + API 422) ; statuts `partially_paid`/`paid` cohérents avec `paid_amount`.
- Rapprochement idempotent, horodaté.
- Relances : zéro doublon (unique document+stage), stages paramétrables, notification i18n ×4, échec Notification isolé.
- Tests verts + PHPStan Strict 0 erreur + Pint PASS + redocly exit 0 ; `Closes #5229` dans la PR ; CHANGELOG.
