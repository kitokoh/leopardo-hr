# Feature Specification: Expense → écritures comptables automatiques (issue #5235)

**Feature Branch**: `mod/expense/5235-expense-accounting`

**Created**: 2026-08-24

**Status**: Draft → Implemented

**Input**: Issue #5235 (Phase C) — « Notes de frais validées (module Expense) et
paie validée (Payroll) → écritures automatiques dans le journal comptable
(avec référence traçable) ». DoD : concordance paie/écritures testée ; aucune
modification du moteur de paie existant (FOCUS intact).

## Problème

1. **Écritures des notes de frais non automatisées** : à l'approbation d'une
   `ExpenseClaim` (module Expense), rien ne génère d'écritures comptables. Le
   comptable doit resaisir les notes de frais dans son outil externe (double
   saisie).
2. **Aucune persistance ni traçabilité** : pas de table d'écritures côté
   Expense, pas de référence stable `note de frais → écritures`, pas
   d'isolation tenant testée pour ce flux.
3. **Le volet Payroll de l'issue est déjà couvert** : #5239 (PR #5394)
   implémente « paie validée → écritures salariales » en consommant
   `PayrollAccountingExportService::journalLines()` (#5256, mergé). Cette PR
   ne traite QUE le volet Expense ; la concordance paie/écritures est
   vérifiée par les tests de #5239 (golden DZ débit = crédit).

## Contraintes de collision (multi-agents, protocole #2400)

- `ExpenseClaimController` (workflow draft → submitted → approved → rejected)
  est **stable sur main** (issues #2677/#4933 mergées) → **aucune modification**
  du contrôleur ni du moteur Expense. Le déclenchement se fait par **observer
  Eloquent** sur le modèle (l'approbation passe par `update()`, un event
  `updated` d'instance est bien émis — contrairement à la validation payroll
  qui est un mass-update, cf. #5239).
- `PayrollAccountingExportService`, `PayrollAccountingEntryService`,
  `payroll_accounting_entries` (#5239, PR #5394 ouverte) → **hors périmètre**,
  aucune modification. Rien à réutiliser de #5239 : les écritures Expense sont
  calculées dans le module Expense (indépendance FOCUS).
- `api/routes/modules/payroll_engine.php`, `payroll-runs/*` (#5239/#5358/#5339)
  → **non touchés**.
- Migrations : `2026_08_24_000001/000002` sur main, `000003/000004` pris par
  #5239 (PR ouverte) → cette PR prend **`2026_08_24_000005`** (vérifié libre
  au moment de l'implémentation).

## Décision

### 1. Persistance des écritures Expense (module Expense)

Nouvelle table `expense_accounting_entries` (migration tenant
`2026_08_24_000005_create_expense_accounting_entries_table`) :
`id, company_id, expense_claim_id, employee_id, date, account_code,
account_label, debit, credit, reference, created_by, timestamps`.
Contrainte d'unicité `(expense_claim_id, account_code)` →
régénération idempotente (remplacement des lignes de la note).

Modèle `ExpenseAccountingEntry` (trait `BelongsToCompany`, relation
`expenseClaim()`).

### 2. Calcul des lignes (partie double, équilibre garanti par construction)

Une note de frais **approuvée** génère deux lignes :
- **Débit** : compte de charge selon la catégorie dominante de la note
  (plan « famille PCG », niveau de confiance `pilot`, documenté — miroir de
  la méthode `PayrollCountryChartOfAccounts` #5256) :
  - `transport` → 6251 « Voyages et déplacements »
  - `meals` → 6256 « Missions (repas) »
  - `accommodation` → 6256 « Missions (hébergement) »
  - `office` → 6064 « Fournitures administratives »
  - `communication` → 626 « Frais postaux et de télécommunications »
  - `other` → 658 « Charges diverses de gestion courante »
- **Crédit** : 425 « Personnel — avances et acomptes (remboursement de frais) »
  — dette de l'entreprise envers l'employé.

Montant = `total_amount` de la note. Le paiement effectif (425 → 512 banque)
reste hors périmètre (le module Expense n'a pas d'étape « payé » API ; le
flux de paiement sera intégré avec la trésorerie du module Accounting).

### 3. Service `ExpenseAccountingEntryService`

- `generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int` —
  exige une note `approved` (RuntimeException sinon), calcule les lignes,
  vérifie l'équilibre débit = crédit (exception
  `UnbalancedExpenseEntriesException` sinon), supprime les lignes existantes
  de la note puis persiste (idempotence), log structuré
  `expense.accounting_entries.generated`.
- `entriesForClaim(ExpenseClaim $claim): Collection` — lecture.
- `balanceForClaim(ExpenseClaim $claim): float` — débits − crédits.

Chaque ligne porte `reference = EXPENSE-{id}` (traçabilité).

### 4. Déclenchement automatique (observer Eloquent)

Nouvel observer `ExpenseAccountingEntryObserver` sur `ExpenseClaim`
(events `updated`) :
- transition vers `approved` → `generateForClaim()` (try/catch : un échec est
  LOGGUÉ, ne casse pas l'approbation — régénération manuelle disponible via
  l'API) ;
- transition depuis `approved` vers `rejected` → suppression des lignes de la
  note (log `expense.accounting_entries.voided`) ;
- enregistré dans `ExpenseServiceProvider::boot()` (fichier vide → zéro
  collision).

### 5. API + RBAC

- `GET /expense-claims/{expenseClaim}/accounting-entries` —
  `api.manager:principal,comptable` (lecture).
- `POST /expense-claims/{expenseClaim}/accounting-entries/regenerate` —
  `api.manager:principal,comptable` + garde défensive
  `hasManagerRole('comptable')` → 403 `INSUFFICIENT_ROLE` (miroir #5239).
- Cross-tenant → 404 (fail-closed, aucune fuite d'existence).

### 6. Traçabilité

Référence `EXPENSE-{id}` + `created_by` + logs structurés
(`expense.accounting_entries.generated|voided|generation_failed`).

## US (User Stories)

- **US1** : l'approbation d'une note de frais génère automatiquement des
  écritures équilibrées (débit charge = crédit personnel), référence
  `EXPENSE-{id}`.
- **US2** : la régénération manuelle (comptable) est idempotente ; un journal
  déséquilibré est refusé (0 ligne persistée).
- **US3** : le rejet d'une note approuvée supprime ses écritures.
- **US4** : lecture principal/comptable, régénération comptable uniquement,
  isolation tenant fail-closed.
- **US5** : aucun impact sur le moteur Expense ni le moteur Payroll (FOCUS).

## DoD

- [ ] Golden : note 10 000 → D 6251 10 000 / C 425 10 000, balance 0.
- [ ] Déclenchement automatique à l'approbation (observer) testé.
- [ ] Idempotence de la régénération testée (2 appels → mêmes lignes).
- [ ] Rejet d'une note approuvée → lignes supprimées, loggé.
- [ ] RBAC ×3 testé (rh refusé, principal lecture seule, comptable écriture).
- [ ] Isolation tenant testée.
- [ ] Aucune modification de `ExpenseClaimController`, du moteur Payroll ni
  des fichiers #5239.
- [ ] OpenAPI : 2 opérations documentées, miroir `dev-hub/openapi/v1.yaml` +
  SDK + MANIFEST régénérés (check `--check` vert).
- [ ] CHANGELOG `[Unreleased]` + doc `docs/expense/EXPENSE_COMPTABILITE.md`.
