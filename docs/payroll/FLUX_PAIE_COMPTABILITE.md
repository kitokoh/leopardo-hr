# Flux Paie → Comptabilité — écritures salariales automatiques + ordre de virement (issue #5239, Phase C)

> **Règle fondatrice** (confirmée fondateur, `COMPTABILITE_CONCEPTION.md` §6.3) :
> le module **Payroll reste maître du calcul** (règles pays, IRG/CNAS, bulletins,
> exports) ; le module **Accounting consomme la paie validée**. Zéro double
> saisie, zéro modification du moteur Payroll (FOCUS).

## Vue d'ensemble

```
┌────────────────────┐   validateRh (mass-update)   ┌────────────────────┐
│  PayrollRun        │ ───────────────────────────▶ │  status = validated│
│  (calculated)      │   + AuditLog                 │  (lock possible)   │
└────────────────────┘   payroll_run_validated      └─────────┬──────────┘
                                                              │
                        PayrollAccountingEntryObserver ◀──────┘ (écoute l'audit)
                                                              │
                                                              ▼
        ┌─────────────────────────────────────────────────────────────┐
        │  PayrollAccountingEntryService::generateForRun($run)        │
        │    · PayrollAccountingExportService::journalLines() (#5256) │
        │    · persiste dans payroll_accounting_entries               │
        │    · équilibre débit = crédit vérifié                       │
        └─────────────────────────────────────────────────────────────┘
                                                              │
                                                              ▼
        ┌─────────────────────────────────────────────────────────────┐
        │  PayrollPaymentOrderService::prepare($run)                  │
        │    · net par employé (bulletins validés)                    │
        │    · fichier banque via BankExportGenerator (SEPA/CSV/...)  │
        │    · statut prepared → executed → reconciled                │
        └─────────────────────────────────────────────────────────────┘
```

## 1. Écritures salariales automatiques

### Déclenchement

`PayrollClosingService::validateRh()` valide le run par **mass-update**
(`PayrollRun::query()->whereKey()->update(...)`), ce qui **n'émet aucun event
Eloquent d'instance**. Le signal fiable de la transition est l'écriture
d'audit `payroll_run_validated` (comportement stable, mergé).

L'observer `PayrollAccountingEntryObserver` (enregistré dans
`PayrollServiceProvider::boot()` via `AuditLog::observe()`) écoute
`AuditLog::created` :
- action ≠ `payroll_run_validated` → ignoré ;
- `auditable_type` ≠ `PayrollRun` → ignoré ;
- sinon → `PayrollAccountingEntryService::generateForRun($run)` en
  `try/catch` : **un échec est loggé, jamais propagé** (la validation RH ne
  casse pas) — une régénération manuelle reste possible via l'API.

### Service

`PayrollAccountingEntryService` (module Payroll, `Infrastructure/Services`) :
- `generateForRun(PayrollRun $run, ?Employee $actor = null): int` —
  exige un run `validated`/`locked`, consomme `journalLines()` (socle #5256,
  lecture seule), vérifie l'équilibre (écart > 0,004 → exception), puis
  **remplace** les lignes du run (idempotence, contrainte unique
  `(payroll_run_id, pay_slip_id, account_code)`) ;
- `entriesForRun(PayrollRun $run): Collection` — lecture ;
- `balanceForRun(PayrollRun $run): float` — écart débit − crédit.

### Comptes (golden DZ)

| Code | Libellé | Sens | Montant (2 bulletins DZ golden) |
|---|---|---|---|
| 641 | Salaires bruts | D | 120 000 |
| 645 | Charges patronales | D | 18 000 |
| 421 | Salaires à payer (net) | C | 100 000 |
| 431 | Cotisations (salariales + patronales) | C | 28 000 |
| 4421 | IR retenu | C | 6 000 |
| 425 | Résidu avances/retenues | C | 4 000 |

**Total débit = total crédit = 138 000** par run (par bulletin aussi).

## 2. Ordre de virement

`PayrollPaymentOrderService` (module Payroll) :
- `prepare(PayrollRun $run, string $format = 'sepa_xml', ?array $companyBank,
  ?Employee $actor = null): PayrollPaymentOrder` —
  net par employé (bulletins `validated`), fichier banque via
  `BankExportGenerator::generate()` (réutilisation des formats existants :
  `sepa_xml`, `csv_generic`, `virement_ma`, `ccp_dz`, `cpa_dz`, `bna_dz`,
  `cnep_dz`, `edx_dz`), persiste l'ordre `prepared` + items (employé, net,
  IBAN), `total_amount` = Σ nets, `transfer_count` = nb employés ;
- `markExecuted($order, string $bankReference, $executedAt, $actor)` —
  statut `executed` + référence banque + date (exige une référence non vide) ;
- `reconcile($order, $actor)` — statut `reconciled` + `reconciled_at`.

## 3. API + RBAC

| Méthode | Route | Rôles |
|---|---|---|
| GET | `/api/v1/payroll-runs/{run}/accounting-entries` | principal, comptable |
| POST | `/api/v1/payroll-runs/{run}/accounting-entries/regenerate` | **comptable** |
| POST | `/api/v1/payroll-runs/{run}/payment-order` | **comptable** |
| GET | `/api/v1/payment-orders` | principal, comptable |
| GET | `/api/v1/payment-orders/{order}` | principal, comptable |
| POST | `/api/v1/payment-orders/{order}/execute` | **comptable** |
| POST | `/api/v1/payment-orders/{order}/reconcile` | **comptable** |

- Groupe route `api.manager:principal,comptable` (déjà existant) + garde
  défensive `hasManagerRole('comptable')` sur les actions d'écriture
  (403 `INSUFFICIENT_ROLE`).
- **RH** : aucun accès aux écritures ni aux ordres (il ne touche qu'au run).
- **Isolation tenant** : `BelongsToCompany` + contrôle `company_id` dans les
  contrôleurs (404 cross-tenant, fail-closed).

## 4. Traçabilité

- Chaque ligne d'écriture porte `reference = PAYROLL-RUN-{id}`,
  `payroll_run_id`, `pay_slip_id`, `employee_id`, `date` et `created_by`.
- Chaque ordre de virement porte `created_by` (préparé par) / `executed_by`
  (exécuté par) et les dates correspondantes ; chaque action est logguée
  (`payroll.accounting_entries.generated`,
  `payroll.payment_order.prepared|executed|reconciled`).

## 5. Hors périmètre (v1)

- Aucune modification du moteur de calcul Payroll ni des règles pays (FOCUS).
- Pas de déclarations sociales automatisées (documentées seulement).
- Pas de comptabilité en partie double complète : le module Accounting
  consommera ces lignes via son propre journal (#5363).
- `PayrollRunController`, `PayrollClosingService`, `BankExportGenerator`,
  `PayrollAccountingExportService` : **non modifiés** (lecture seule) —
  PRs en cours #5358/#5339/#5322.

## 6. Tests

`api/tests/Feature/Payroll/` :
- `PayrollAccountingEntriesFlowTest` (10) : déclenchement par l'audit,
  golden DZ équilibré, idempotence, exige run validé, **refus d'un journal
  déséquilibré** (aucune ligne persistée), isolation tenant, audit
  hors-sujet ignoré, échec loggé sans propagation, RBAC API.
- `PayrollPaymentOrderFlowTest` (9) : préparation (golden 100 000/2),
  exige run validé, exécution + référence, statuts, rapprochement,
  RBAC API (principal/RH refusés, comptable OK), cycle API complet.
