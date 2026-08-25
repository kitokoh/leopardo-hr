# PERF — Métriques de charge & audit des index (issue #5284)

> **Statut** : mesures locales (2026-08-23, PostgreSQL 14, PHP 8.4, sandbox
> dédié) + référence F-12 officielle (2026-08-09, PG 16). Cible issue #5284 :
> **run de paie 500 employés < 60 s** → **atteinte (3,50 s)**.

## 1. Parcours critiques — métriques

### 1.1 Clôture de paie (calculate → validate-rh → lock)

Protocole : `dev-hub/tools/payroll-benchmark.sh --employees=500 --step=all`
(jeu DZ réaliste `PayrollBenchmarkSeeder`, commande `payroll:benchmark`).

| Métrique | 500 employés (PG14, sandbox) |
|---|---|
| Seed du jeu | 0,35 s |
| **calculate** | **3,48 s** (7,0 ms/employé) |
| validate-rh | 0,00 s |
| **lock (API)** | **0,01 s** |
| **Total clôture** | **3,50 s** ✅ < 60 s |
| Requêtes SQL | 4 020 (8,0 /employé — barrière N+1 = 20, aucun N+1) |
| Pic mémoire | 4,0 Mo |

**Référence F-12** (docs/payroll/BENCHMARK.md, 2026-08-09, PG 16) :
**10 000 employés = 90,15 s** < objectif 30 min (1800 s) ✅.

**⚠️ Artefact de mesure à connaître** : avec `QUEUE_CONNECTION=sync`, l'étape
`lock` inclut la génération **synchrone** des PDF de bulletins
(`ArchivePaySlipsToCabinetJob`, queue `pdf`) : 104 s pour 500 bulletins. En
production (queue `database`/Redis + worker), le lock API reste à ~0,01 s et
les PDF sont archivés en arrière-plan — **ne jamais mesurer la clôture avec
une queue sync**.

### 1.2 Signup trial (100 signups/jour)

- Charge cible : 100 signups/j ≈ 1 toutes les 14 min — volume trivial pour le
  provisioning (création entreprise + schéma tenant + employés, hors ligne).
- Le parcours signup → provision est couvert de bout en bout par les tests
  Feature (`TrialSignupTest`, `ProvisionDemoTenantJobTest`, course
  slug-collision #5290) ; aucune mesure de latence supplémentaire requise à ce
  volume (le goulot potentiel serait le worker de provisioning, drainé toutes
  les 5 min — voir `docs/ops/DEPLOYMENT_URLS.md`).
- Facture : le parcours facture dépend du module Comptabilité (greenfield,
  waves W2→W4) — mesuré à la livraison du module (#5274 E2E).

## 2. Audit des index (tables volumineuses)

Méthode : FK sans index (`pg_constraint`/`pg_index`) + `pg_stat_user_tables`
(seq scans) + patterns de requêtes sur le jeu de benchmark.

### 2.1 Index ajoutés — migration `2026_08_23_000001_add_perf_audit_indexes`

| Table | Index | Justification |
|---|---|---|
| `webhook_deliveries` | `(webhook_endpoint_id, delivered_at)` | Table qui grossit avec les retries webhook ; requêtes « livraisons en attente d'un endpoint » (`delivered_at IS NULL`) + historique récent d'un endpoint |
| `audit_logs` | `(employee_id)` | FK ajoutée après création ; piste d'audit par employé (RBAC, GDPR, workflows) en seq-scan |
| `leave_accruals` | `(leave_policy_id)` | Reporting/audit des acquisitions par politique de congés (#5289) |
| `approval_decisions` | `(approval_request_id)` | Chaîne de décisions lue pour chaque demande d'approbation |

Garde de non-régression : `tests/Feature/DataModel/PerfAuditIndexesTest`
(vérifie la présence des 4 index à chaque run CI).

### 2.2 Tables déjà correctement indexées (constat, rien à faire)

- `pay_slips` : unique `(payroll_run_id, employee_id)` + `(company_id,
  employee_id, period_start)` ✅
- `pay_slip_lines` : `(pay_slip_id, type, order)` ✅ (0,8 % de seq scans)
- `payroll_runs` : `(company_id, status)` + `(company_id, period_start,
  period_end)` ✅
- `payment_documents` / `cabinet_documents` : index company/document_type/
  status/employee/folder/source ✅
- `attendance_logs` : unique `(employee_id, date, session_number)` +
  `(employee_id, date)` + `(date, status)` + `created_at` ✅
- `employees` : index company + composites company/employee sur les tables
  chaudes (constitution IX, #3947) ✅

## 3. Recommandations (hors périmètre de cette issue)

- Rejouer `payroll:benchmark --employees=10000` après chaque changement du
  moteur paie (garde F-12 déjà en place via `payroll-benchmark.sh`).
- Mesurer la clôture avec `QUEUE_CONNECTION=redis`/`database` (jamais `sync`).
- Index supplémentaires à évaluer quand les volumes le justifient :
  `contract_amendments (contract_id)`, `employees (position_id/site_id)` —
  faible coût actuel, à activer sur signal (seq scans persistants).
