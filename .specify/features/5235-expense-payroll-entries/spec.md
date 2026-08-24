# Feature Specification: Intégration Expense + Payroll → écritures comptables (issue #5235, Phase C)

**Feature Branch**: `mod/expense/5235-expense-payroll-entries`

**Created**: 2026-08-24

**Status**: Draft → Implemented

**Input**: Issue #5235 — notes de frais validées (module Expense) et paie
validée (Payroll) → écritures automatiques dans le journal comptable avec
référence traçable. DoD : concordance paie/écritures testée ; aucune
modification du moteur de paie (FOCUS intact).

## Cadrage

La partie **Payroll → écritures** est livrée par la PR **#5394** (issue
#5239) : `payroll_accounting_entries` + observer sur la validation du run
(`PayrollAccountingEntryService`, socle `journalLines()` #5256 mergé).

Cette spec couvre la partie **Expense → écritures** : à l'approbation d'une
note de frais (`ExpenseClaim` statut `approved`), générer les écritures
comptables (D compte de charges / C compte banque) avec référence
`EXPENSE-CLAIM-{id}`.

## Problème

1. Rien ne génère d'écritures comptables à l'approbation d'une note de
   frais : les dépenses validées sont invisibles pour la comptabilité.
2. Le module Expense est minimal (contrôleur + provider vides) : aucun
   service métier, aucun point d'extension — l'approbation (`ExpenseClaimController::approve`)
   fait un `update()` d'instance (events Eloquent déclenchés) sans aucun
   effet de bord comptable.
3. Traçabilité absente : pas de référence note → écriture.

## Contraintes de collision (multi-agents, protocole #2400)

- Module **Expense** : libre (vérifié via API — aucune PR ouverte ne le
  touche). Le contrôleur `ExpenseClaimController` existant est **non modifié**
  (l'observer Eloquent suffit — `approve()` fait un `update()` d'instance).
- Module **Accounting** : occupé (#5363 journal, #5357/#5363/#5365…) →
  **aucune modification** ; le module consommera ces lignes plus tard.
- Module **Payroll** : occupé (#5358/#5339/#5321/#5317, + ma PR #5394) →
  **aucune modification** (la partie paie est déjà traitée par #5394).
- `App\Modules\Planning\Domain\Models\ExpenseClaim` : lu en **lecture
  seule** (pas de modification du modèle).
- Migration : `2026_08_24_000005` (libre — 000001/000002 sur main,
  000003/000004 dans ma PR #5394).

## Décision

### 1. Persistance (module Expense)

Nouvelle table `expense_accounting_entries` (migration tenant
`2026_08_24_000005_create_expense_accounting_entries_table`) :
`id, company_id, expense_claim_id, date, account_code, account_label,
debit, credit, reference, created_by, timestamps`. Contrainte d'unicité
`(expense_claim_id, account_code)` → régénération idempotente.

Modèle `ExpenseAccountingEntry` (`App\Modules\Expense\Domain\Models`,
trait `BelongsToCompany`).

### 2. Service

`ExpenseAccountingEntryService` (`App\Modules\Expense\Infrastructure\Services`) :
- `generateForClaim(ExpenseClaim $claim, ?Employee $actor = null): int` —
  exige un claim `approved`, produit **2 lignes équilibrées** par construction
  (D 625 « Frais généraux » = total_amount, C 512 « Banque » = total_amount),
  remplace les lignes existantes du claim (idempotence), log + trace.
- `entriesForClaim(ExpenseClaim $claim): Collection` — lecture.
- `balanceForClaim(ExpenseClaim $claim): float` — écart débit − crédit.

Comptes par défaut (PCG minimal, surchargeable via le plan comptable
Accounting plus tard) : **625** (charges) / **512** (banque). Référence
traçable : `EXPENSE-CLAIM-{id}`.

### 3. Déclenchement automatique

Nouvel observer `ExpenseAccountingEntryObserver` sur `ExpenseClaim`
(evt `saved`) : si `status === 'approved'` et `approved_at !== null` →
`generateForClaim()` en `try/catch` (un échec est loggé, **ne casse pas**
l'approbation). Enregistré dans `ExpenseServiceProvider::boot()` (fichier
vide → zéro collision). Idempotent : re-sauvegarde → remplacement propre.

### 4. API + RBAC

Nouveau contrôleur `ExpenseAccountingController` (routes dans
`api/routes/modules/expense.php`, sous-groupe `api.manager:principal,comptable`) :
- `GET  /expense-claims/{claim}/accounting-entries` — principal/comptable
  (RH/dept/marketing/employé exclus par le middleware).
- `POST /expense-claims/{claim}/accounting-entries/regenerate` —
  **comptable** (garde défensive `hasManagerRole('comptable')` →
  403 `INSUFFICIENT_ROLE`, régénération idempotente).

RH/employé : aucun accès aux écritures de notes de frais.

## User Scenarios & Testing

### User Story 1 — Une note de frais approuvée génère ses écritures automatiquement (Priority: P1)

**Independent Test**: `php artisan test --filter=ExpenseAccountingEntriesFlowTest`

**Acceptance Scenarios** :
- L'approbation d'un claim (status → `approved` via `update()` d'instance)
  déclenche l'observer → 2 lignes `expense_accounting_entries`.
- Golden : note 1 000,00 → D 625 = 1 000,00 · C 512 = 1 000,00 ;
  **débit = crédit = 1 000,00**.
- Chaque ligne porte `reference = EXPENSE-CLAIM-{id}` et le `expense_claim_id`.
- Régénération idempotente : 2 appels → même nombre de lignes.
- Isolation tenant : les lignes d'un claim société A ne sont pas vues par B.
- Un claim non approuvé → `generateForClaim` refuse (RuntimeException).
- Un `rh`/employé ne peut pas lire ; un `principal` lit ; un `comptable`
  régénère.
- **Rejet après approbation** (workflow autorisé) → écritures supprimées
  (une note rejetée ne reste pas au passif comptable).

### User Story 2 — Échec de génération loggé, jamais propagé (Priority: P2)

**Acceptance Scenarios** :
- Un échec de `generateForClaim` pendant l'approbation est loggué
  (`expense.accounting_entries.generation_failed`) et l'approbation reste
  effective (l'employé n'est pas bloqué).

## Hors périmètre

- Aucune modification du moteur de paie (FOCUS) ni de
  `PayrollAccountingEntryService` (déjà livré #5394).
- Aucune modification du module Accounting ni du journal #5363.
- Pas de plan comptable paramétrable par entreprise en v1 (comptes 625/512
  par défaut, documentés — surcharge ultérieure via le plan comptable).

## DoD

- [x] Spec approuvée avant code (ce fichier, constitution §I).
- [x] Écritures équilibrées pour une note de frais réelle (golden test).
- [x] Concordance note/écritures testée ; isolation tenant testée.
- [x] Déclenchement automatique à l'approbation (observer Eloquent).
- [x] Échec loggé sans propagation (test).
- [x] RBAC principal/comptable/RH testé.
- [x] OpenAPI (2 opérations + schéma) + SDK régénérés.
- [x] CHANGELOG `[Unreleased]`.
