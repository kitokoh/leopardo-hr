# Rapport de maturité — BC-07 PAYROLL

> **DEP-BC07 (issue #5883)** — Deep maturity, BC-07 Payroll.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : wave maturité.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-07).

## Périmètre

PAYROLL = périodes, règles, snapshots, calculs, bulletins et exports :
`api/app/Modules/Payroll` (20+ contrôleurs API, moteur `PayrollCalculator`,
générateurs d'exports bancaires), routes `/api/v1/payroll*` (groupe
`payroll-sensitive`), policies `PayrollPolicy`/`PayrollAuditPolicy`, machine à
états `PayrollRun` (draft → calculating → calculated → validated → locked →
paid, + cancelled/error).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Vocabulaire riche et stable (Run/Slip/Slab/SocialContribution/BankExport), machine à états explicite (`PayrollRun::STATUS_*`), invariants métier documentés (golden tests #5149, README `api/app/Modules/Payroll/Application`). |
| D2 | Données | 🟢 PRÉSENT | Tables tenant migrées avec index (snapshots paie, `payroll_runs`, `pay_slips`, exports bancaires), `company_id` partout, colonnes calculées persistées (total_gross/net/employer_cost). |
| D3 | Tenant | 🟢 PRÉSENT | `PayrollPolicy` vérifie `company_id` sur CHAQUE action (calculateRun/validateRun/cancelRun/viewSlip/downloadPdf/sendSlips/generateBankExport) + guards 404 cross-tenant dans le contrôleur. `BelongsToCompany` sur les modèles. |
| D4 | API | 🟢 PRÉSENT | Routes versionnées `/payroll-runs` (index/store/show/calculate/validate/lock/unlock/cancel) + `/payroll/simulate`, `/pay-slips/*`, exports — documentées OpenAPI (couverture verrouillée). Requests validées. |
| D5 | Autorisation | 🟢 PRÉSENT | RBAC fin : `principal`/`comptable` pour validate/lock/unlock (séparation des tâches #5246), `rh` peut préparer/calculer, employé ne voit que ses bulletins. `INSUFFICIENT_ROLE` explicite. |
| D6 | Transactions | 🟢 PRÉSENT | Machine à états gardée : recalcul interdit hors draft/calculated (422), cancel interdit sur paid/cancelled/locked (422), unlock motivé obligatoire, tout échec de calculate ramène à draft (recalculable, #2555). |
| D7 | Asynchronisme | 🟡 PARTIEL | `processing`/`error` pour les runs batch asynchrones (statuts prévus) ; pas d'outbox/inbox propre (MAT-008 en cours). |
| D8 | Sécurité | 🟢 PRÉSENT | Données paie (salaires, IBAN) protégées : policy par bulletin, PDF bornés au tenant, exports bancaires réservés principal/rh, audit trail (`payroll_run_locked`, `PayrollAuditPolicy`). |
| D9 | Frontend | 🟢 PRÉSENT | Web client (bulletins, exports), mobile employee (bulletins). Non audité en profondeur. |
| D10 | Performance | 🟢 PRÉSENT | Benchmark 10k employés (plan DZ #5149), pagination bornée, index de calcul. Budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks paie DZ, exports, golden tests CI ; observabilité structurée. |
| D12 | Produit | 🟢 PRÉSENT | Golden tests ≥ 40 (plan DZ), parcours clôture 2 étapes (validate + lock), exports DZ — le pilote DZ a tiré le BC vers le haut. Manque un golden journey versionné multi-pays (MAT-013 en cours). |

## Correctif livré (PR de ce DEP)

**Verrouillage test des invariants du cycle de vie `PayrollRun` et de
l'isolation cross-tenant** (D3/D6/D8) — `api/tests/Feature/Payroll/PayrollRunInvariantTest.php`
(4 scénarios, deux tenants) :

- `GET /payroll-runs/{runB}` (manager A) → **404** ;
- `POST /payroll-runs/{runB}/calculate` (manager A) → **404** ;
- `POST /payroll-runs/{run locked}/cancel` → **422** (`PAYROLL_RUN_CANCEL_NOT_ALLOWED`) ;
- `POST /payroll-runs/{run locked}/unlock` sans raison → **422** (déverrouillage
  motivé obligatoire).

## Recommandations (non bloquantes, PR futures)

1. **Étendre le verrouillage des invariants** (D6) : tests de transition complets
   de la machine à états (calculating/processing/error, validate sur draft,
   lock sans validate, paid irreversibilité) — compléter le contrat existant.
2. **Policies sur les contrôleurs restants** (D5) : `LedgerController`,
   `BankExportController`, `PaymentBatchController` n'appellent pas `authorize`
   visible — vérifier la couverture policy de chaque action (les guards 404
   inline protègent déjà le cross-tenant).
3. **Golden journey multi-pays** (D12) : étendre les golden tests DZ à un second
   pays (CEMAC/CEDEAO) une fois MAT-007 mergé.

## Non-régression

Aucun code de production modifié — correctif purement contractuel (tests +
rapport). Les 4 scénarios verrouillent le comportement existant.
