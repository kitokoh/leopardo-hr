# Flux Expense → Comptabilité — notes de frais → écritures comptables (issue #5235, Phase C)

> **Règle fondatrice** (confirmée fondateur, `COMPTABILITE_CONCEPTION.md` §6.2) :
> le module **Expense reste maître du workflow des notes de frais** ; le module
> **Accounting consomme les notes approuvées**. Zéro double saisie, zéro
> modification du moteur Expense existant (FOCUS). Le volet paie de l'issue
> #5235 est traité par #5239 (`docs/payroll/FLUX_PAIE_COMPTABILITE.md`).

## Vue d'ensemble

```
ExpenseClaim approuvée (manager, ExpenseClaimController::approve)
   │  update() → event Eloquent `updated` (transition submitted → approved)
   ▼
ExpenseAccountingEntryObserver (module Expense, enregistré dans
ExpenseServiceProvider::boot())
   │
   ▼
ExpenseAccountingEntryService::generateForClaim($claim)
   │  · partie double : D charge (catégorie dominante) / C 425 personnel
   │  · persiste dans expense_accounting_entries
   │  · équilibre débit = crédit vérifié (exception sinon)
   │  · référence traçable EXPENSE-{id}
   ▼
Module Accounting : consommation des lignes (journal, exports expert-comptable)
```

## 1. Écritures automatiques

### Déclenchement

L'approbation d'une note passe par `ExpenseClaimController::approve()` →
`$expenseClaim->update([...])` : un **event Eloquent `updated` d'instance est
bien émis** (contrairement à la validation payroll, mass-update sans event —
cf. #5239). L'observer `ExpenseAccountingEntryObserver` (enregistré dans
`ExpenseServiceProvider::boot()` via `ExpenseClaim::observe()`) écoute
`updated` :

- transition vers `approved` → `ExpenseAccountingEntryService::generateForClaim($claim)`
  en `try/catch` : **un échec est loggé, jamais propagé** (l'approbation ne
  casse pas) — une régénération manuelle reste possible via l'API ;
- transition depuis `approved` (rejet) → suppression des lignes de la note
  (`voidForClaim`, log `expense.accounting_entries.voided`).

### Service

`ExpenseAccountingEntryService` (module Expense, `Infrastructure/Services`) :

- `generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int` —
  exige une note `approved`, calcule les lignes (partie double), vérifie
  l'équilibre (écart > 0,004 → `UnbalancedExpenseEntriesException`), puis
  **remplace** les lignes de la note (idempotence, contrainte unique
  `(expense_claim_id, account_code)`) ;
- `entriesForClaim(ExpenseClaim $claim): Collection` — lecture ;
- `balanceForClaim(ExpenseClaim $claim): float` — débits − crédits ;
- `voidForClaim(ExpenseClaim $claim, ?Employee $actor = null): int` — annulation.

### Comptes utilisés (plan « famille PCG », confiance `pilot`)

Référentiel `ExpenseClaimChartOfAccounts` (miroir de
`PayrollCountryChartOfAccounts` #5256) — à valider par un expert-comptable
local avant généralisation :

| Catégorie dominante | Débit | Crédit |
|---|---|---|
| transport | 6251 Voyages et déplacements | 425 Personnel |
| meals | 6256 Missions (repas) | 425 Personnel |
| accommodation | 6256 Missions (hébergement) | 425 Personnel |
| office | 6064 Fournitures administratives | 425 Personnel |
| communication | 626 Frais postaux et de télécommunications | 425 Personnel |
| other | 658 Charges diverses de gestion courante | 425 Personnel |

La catégorie dominante est celle dont le montant cumulé est le plus élevé
(départage stable par ordre alphabétique → déterminisme de la régénération).
Le paiement effectif (425 → 512 banque) reste hors périmètre : le module
Expense n'a pas d'étape « payé » API ; le flux de paiement sera intégré avec
la trésorerie du module Accounting (#5229).

## 2. API

- `GET /api/v1/expense-claims/{expenseClaim}/accounting-entries` —
  RBAC `api.manager:principal,comptable` (lecture) ;
- `POST /api/v1/expense-claims/{expenseClaim}/accounting-entries/regenerate` —
  RBAC `api.manager:principal,comptable` + garde défensive
  `hasManagerRole('comptable')` → 403 `INSUFFICIENT_ROLE` (miroir #5239).

Isolation tenant fail-closed : cross-tenant → 404 (aucune fuite d'existence).

## 3. Traçabilité

Chaque ligne porte `reference = EXPENSE-{id}`, `expense_claim_id`,
`created_by`, `date` (date d'approbation). Logs structurés :
`expense.accounting_entries.generated|voided|generation_failed`.

## 4. DoD

- [x] Golden : note 10 000 → D 6251 10 000 / C 425 10 000, balance 0.
- [x] Déclenchement automatique à l'approbation (observer) testé.
- [x] Idempotence de la régénération testée.
- [x] Rejet d'une note approuvée → lignes supprimées, loggé.
- [x] RBAC ×3 testé (rh refusé, principal lecture seule, comptable écriture).
- [x] Isolation tenant testée.
- [x] Aucune modification de `ExpenseClaimController`, du moteur Payroll ni
  des fichiers #5239.
- [x] OpenAPI : 2 opérations documentées, miroir + SDK + MANIFEST régénérés.
- [x] CHANGELOG `[Unreleased]` + cette doc.
