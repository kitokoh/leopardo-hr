# Feature Specification: Flux Paie → Comptabilité — écritures salariales automatiques + ordre de virement (issue #5239)

**Feature Branch**: `mod/payroll/5239-flux-paie-compta`

**Created**: 2026-08-24

**Status**: Draft → Implemented

**Input**: Issue #5239 (Phase C) — le module **Payroll reste maître du calcul**
(règles pays, bulletins, exports) ; le module **Accounting consomme la paie
validée**. Zéro double saisie, zéro modification du moteur Payroll (FOCUS).

## Problème

1. **Écritures salariales non automatisées** : à la validation d'un
   `PayrollRun`, rien ne génère les écritures comptables. Le socle
   `PayrollAccountingExportService::journalLines()` (#5256, mergé) sait
   produire les lignes (D 641 brut · D 645 charges patronales · C 421 net ·
   C 431 cotisations · C 4421 impôt · C 425 résidu) mais n'est pas branché
   sur le cycle de vie du run.
2. **Aucune persistance** : `journalLines()` retourne un tableau en mémoire ;
   pas de table, pas de traçabilité run → écritures, pas d'isolation tenant
   testée.
3. **Pas d'ordre de virement** : le net par employé d'un run validé ne peut
   pas être transformé en ordre de virement préparé → exécuté par le
   comptable (référence banque + date) avec rapprochement.
4. **Rôles non couverts** : rien ne distingue comptable (enregistre/exécute),
   principal (lecture) et RH (ne touche qu'au run).

## Contraintes de collision (multi-agents, protocole #2400)

- `PayrollRunController::validateRun`, `PayrollClosingService` sont **occupés**
  (PRs #5358/#5339) → **aucune modification** de ces fichiers.
- `PayrollAccountingExportService`, `BankExportGenerator`,
  `BankExportController` sont **occupés** (PR #5322) → **lecture seule** :
  on les *appelle*, on ne les modifie pas.
- `PayrollRun::query()->whereKey()->update()` (mass update) ne déclenche
  **pas** les events Eloquent d'instance → le déclenchement automatique se
  fait par **observer sur `AuditLog`** (action `payroll_run_validated`,
  écrit par `PayrollClosingService::writeAudit()` — comportement stable,
  testé, déjà mergé).
- Migrations : `2026_08_24_000001/000002` existent sur main ou en PR ;
  on prend **000003** (entries) et **000004** (payment orders), vérifiées
  libres au moment de l'implémentation.

## Décision

### 1. Persistance des écritures salariales (module Payroll)

Nouvelle table `payroll_accounting_entries` (migration tenant
`2026_08_24_000003_create_payroll_accounting_entries_table`) :
`id, company_id, payroll_run_id, pay_slip_id, employee_id, date,
account_code, account_label, debit, credit, reference, created_by, timestamps`.
Contrainte d'unicité `(payroll_run_id, pay_slip_id, account_code)` →
régénération idempotente (remplacement des lignes du run).

Modèle `PayrollAccountingEntry` (trait `BelongsToCompany`).

Service `PayrollAccountingEntryService` :
- `generateForRun(PayrollRun $run, ?Employee $actor = null): int` —
  consomme `journalLines()`, supprime les lignes existantes du run,
  persiste les nouvelles, **vérifie l'équilibre débit = crédit**
  (exception `UnbalancedPayrollEntriesException` sinon), log + audit.
- `entriesForRun(PayrollRun $run): Collection` — lecture.
- `balanceForRun(PayrollRun $run): float` — somme débits − somme crédits.

### 2. Déclenchement automatique (observer AuditLog)

Nouvel observer `PayrollAccountingEntryObserver` sur `AuditLog` (evt `created`) :
si `$auditLog->action === 'payroll_run_validated'` → résoudre le
`PayrollRun` (via `auditable_id`) et appeler `generateForRun()` en
`try/catch` (un échec est loggé, **ne casse pas** la validation). Enregistré
dans `PayrollServiceProvider::boot()` (fichier vide → zéro collision).
Idempotent : re-validation → remplacement propre.

### 3. Ordre de virement (module Payroll)

Tables `payroll_payment_orders` + `payroll_payment_order_items`
(migration `2026_08_24_000004_create_payroll_payment_orders_table`) :
- order : `id, company_id, payroll_run_id, status [prepared|executed|reconciled],
  format, file_path, total_amount, transfer_count, bank_reference,
  executed_by, executed_at, reconciled_at, created_by, timestamps`
- items : `id, payment_order_id, employee_id, net_amount, iban`

Service `PayrollPaymentOrderService` :
- `prepare(PayrollRun $run, string $format = 'sepa_xml', ?array $companyBank,
  ?Employee $actor = null): PayrollPaymentOrder` — agrège le net par employé
  (bulletins `validated`), appelle `BankExportGenerator::generate()` (lecture
  seule) pour le contenu, stocke le fichier, crée l'ordre + items, statut
  `prepared`, logs structurés `payroll.payment_order.*`.
- `markExecuted(PayrollPaymentOrder $order, string $bankReference,
  ?Carbon $executedAt, ?Employee $actor = null)` — statut `executed` +
  référence banque + date, logs.
- `reconcile(PayrollPaymentOrder $order, ?Employee $actor = null)` — statut
  `reconciled`, logs.

**Traçabilité** : chaque écriture porte `reference = PAYROLL-RUN-{id}` +
`created_by` ; chaque ordre porte `created_by` / `executed_by` / dates ; les
actions sont logguées (`payroll.accounting_entries.generated`,
`payroll.payment_order.prepared|executed|reconciled`).

### 4. API + RBAC

Nouveau contrôleur `PayrollAccountingController` (routes dans
`payroll_engine.php`, groupe existant `api.manager:principal,comptable`) :
- `GET  /payroll-runs/{run}/accounting-entries` — principal/comptable (lecture).
- `POST /payroll-runs/{run}/accounting-entries/regenerate` — **comptable**
  (garde défensive `hasManagerRole('comptable')` → 403 `INSUFFICIENT_ROLE`,
  régénération idempotente).

Nouveau contrôleur `PayrollPaymentOrderController` :
- `POST /payroll-runs/{run}/payment-order` — **comptable** (garde défensive).
- `GET  /payment-orders` — principal/comptable.
- `GET  /payment-orders/{order}` — principal/comptable.
- `POST /payment-orders/{order}/execute` — **comptable**
  (body `{bank_reference, executed_at?}`).
- `POST /payment-orders/{order}/reconcile` — **comptable**.

RH : aucun accès aux écritures/ordres (il ne touche qu'au run).

## User Scenarios & Testing

### User Story 1 — Un run de paie DZ validé génère ses écritures automatiquement (Priority: P1)

**Independent Test**: `php artisan test --filter=PayrollAccountingEntriesFlowTest`

**Acceptance Scenarios** :
- La validation RH d'un run (`AuditLog` action `payroll_run_validated`)
  déclenche `generateForRun` → des lignes `payroll_accounting_entries`
  existent pour le run.
- Golden DZ (2 bulletins brut 60 000, cot. sal. 5 000, impôt 3 000,
  autres 2 000, net 50 000, patronales 9 000) : D 641 = 120 000 ·
  D 645 = 18 000 · C 421 = 100 000 · C 431 = 28 000 · C 4421 = 6 000 ·
  C 425 = 4 000 ; **débit = crédit = 138 000** (cf. socle #5256).
- Chaque ligne porte `reference = PAYROLL-RUN-{id}` et le `payroll_run_id`.
- Régénération idempotente : 2 appels → même nombre de lignes.
- Isolation tenant : les lignes d'un run société A ne sont pas vues par B.
- Un `rh` ne peut ni lire ni régénérer ; un `principal` lit ; un `comptable`
  régénère.
- Journal déséquilibré (débit ≠ crédit) → `UnbalancedPayrollEntriesException`
  et **aucune** ligne persistée.

### User Story 2 — Le comptable prépare, exécute et rapproche un ordre de virement (Priority: P1)

**Independent Test**: `php artisan test --filter=PayrollPaymentOrderFlowTest`

**Acceptance Scenarios** :
- `prepare` sur un run validé → ordre `prepared`, `total_amount` = Σ nets,
  `transfer_count` = nb employés, fichier généré (`file_path` non nul),
  items par employé (net + iban).
- `markExecuted` avec référence banque + date → `executed`.
- `reconcile` → `reconciled`.
- Un `rh` ne peut pas préparer/exécuter ; un `comptable` oui.

### User Story 3 — Équilibre garanti (Priority: P1)

**Acceptance Scenarios** :
- `generateForRun` lève `UnbalancedPayrollEntriesException` si le total
  débit ≠ total crédit (couverture défensive du service, testée par
  simulation d'un journal déséquilibré).

## Hors périmètre

- Aucune modification du moteur de calcul Payroll (FOCUS) ni des règles pays.
- Pas de déclarations sociales automatisées (documentées seulement).
- Pas de comptabilité en partie double complète avant Phase C (le module
  Accounting consommera ces lignes via son propre journal — #5363).
- Pas de modification de `PayrollRunController`, `PayrollClosingService`,
  `BankExportGenerator`, `PayrollAccountingExportService` (PRs en cours).

## DoD

- [x] Spec approuvée avant code (ce fichier, constitution §I).
- [x] Écritures équilibrées pour un run DZ réel (golden test).
- [x] Virement préparé → exécuté → rapproché (test).
- [x] Traçabilité run → écritures vérifiée ; isolation tenant testée.
- [x] Déclenchement automatique à la validation (observer AuditLog).
- [x] RBAC comptable/principal/RH testé.
- [x] CHANGELOG `[Unreleased]`.
