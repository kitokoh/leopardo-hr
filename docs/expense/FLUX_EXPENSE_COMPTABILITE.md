# Expense + Payroll → écritures comptables (issue #5235, Phase C)

> **Règle fondatrice** (`COMPTABILITE_CONCEPTION.md` §6.3) : le module
> **Payroll reste maître du calcul** et le module **Expense** reste maître de
> ses notes de frais ; la comptabilité **consomme** les écritures validées.
> Zéro double saisie, zéro modification du moteur de paie (FOCUS).

## Vue d'ensemble

```
ExpenseClaim (submitted)
      │  ExpenseClaimController::approve()  (update() d'INSTANCE → events Eloquent)
      ▼
ExpenseAccountingEntryObserver (saved)
      │  statut == approved && approved_at != null
      ▼
ExpenseAccountingEntryService::generateForClaim($claim)
      │  D 625 « Frais généraux » = total · C 512 « Banque » = total
      │  reference = EXPENSE-CLAIM-{id} · équilibre débit = crédit par construction
      ▼
expense_accounting_entries (migration 2026_08_24_000005)
```

La partie **Payroll → écritures** est livrée par la PR #5394 (issue #5239 :
`payroll_accounting_entries` + observer sur la validation du run).

## 1. Déclenchement

`ExpenseClaimController::approve()` fait un `update()` d'instance
(`status → approved`, `approved_by`, `approved_at`) — les events Eloquent
`saved` sont donc émis sans aucune modification du contrôleur.

L'observer `ExpenseAccountingEntryObserver` (enregistré dans
`ExpenseServiceProvider::boot()` via `ExpenseClaim::observe()`) :
- statut ≠ `approved` ou `approved_at` null → ignoré ;
- sinon → `ExpenseAccountingEntryService::generateForClaim($claim)` en
  `try/catch` : **un échec est loggé, jamais propagé** (l'approbation ne
  casse pas) — une régénération manuelle reste possible via l'API.

## 2. Service

`ExpenseAccountingEntryService` (module Expense) :
- `generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int` —
  exige un claim `approved`, persiste **2 lignes équilibrées par
  construction** (D 625 / C 512 = `total_amount`), remplace les lignes
  existantes (idempotence, contrainte unique `(expense_claim_id,
  account_code)`), log + `created_by` ;
- `entriesForClaim(ExpenseClaim $claim): Collection` — lecture ;
- `balanceForClaim(ExpenseClaim $claim): float` — écart débit − crédit (0.0).

### Comptes par défaut (PCG minimal v1)

| Code | Libellé | Sens |
|---|---|---|
| 625 | Frais généraux | D |
| 512 | Banque | C |

Surcharge ultérieure possible via le plan comptable du module Accounting
(paramétrage par entreprise).

## 3. API + RBAC

| Méthode | Route | Rôles |
|---|---|---|
| GET | `/api/v1/expense-claims/{claim}/accounting-entries` | principal, comptable |
| POST | `/api/v1/expense-claims/{claim}/accounting-entries/regenerate` | **comptable** |

- Groupe route `api.manager` (existant) + garde défensive
  `hasManagerRole('comptable')` → 403 `INSUFFICIENT_ROLE`.
- **RH / employé** : aucun accès aux écritures (l'employé ne touche qu'à sa
  note).
- **Isolation tenant** : `BelongsToCompany` + contrôle `company_id` dans le
  contrôleur (404 cross-tenant, fail-closed).

## 4. Traçabilité

- Chaque ligne porte `reference = EXPENSE-CLAIM-{id}`, `expense_claim_id`,
  `date` (= `approved_at`) et `created_by`.
- Chaque génération est logguée (`expense.accounting_entries.generated`,
  `expense.accounting_entries.generated_via_observer`,
  `expense.accounting_entries.generation_failed`).

## 5. Hors périmètre (v1)

- Aucune modification du moteur de paie (FOCUS) ni du
  `PayrollAccountingEntryService` (#5394).
- Aucune modification du module Accounting (journal #5363 en cours) : les
  lignes seront consommées par le module comptable.
- Pas de plan comptable paramétrable par entreprise en v1 (comptes 625/512
  par défaut).

## 6. Tests

`api/tests/Feature/Expense/ExpenseAccountingEntriesFlowTest` (10) :
déclenchement par l'approbation, golden D 625/C 512 = 1 000,00 équilibré,
idempotence, exige statut approved, isolation tenant, échec loggé sans
propagation, suppression des écritures au rejet après approbation,
RBAC API (RH/employé/principal refusés, comptable OK).
