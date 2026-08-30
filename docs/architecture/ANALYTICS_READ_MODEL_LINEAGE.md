# BC-22 — Lineage des read models Analytics & Reporting

> **Issue :** DEP-BC22 #5898 — Deep maturity BC-22 Analytics & Reporting.
> **Statut :** livré (2026-08-30) — « source → agrégat → endpoint → export ».
> **Rapport de maturité :** `docs/architecture/maturity/BC-22-ANALYTICS-MATURITY.md`.

Ce document cartographie **l'origine de chaque donnée affichée ou exportée**
par le reporting Leopardo (read models, agrégats, exports). Il sert de
référence pour :

- comprendre d'où vient un chiffre (audit, support) ;
- vérifier qu'aucun read model ne lit une table hors de son tenant ;
- décider où introduire un snapshot si les volumes deviennent critiques ;
- garantir que **deux recalculs produisent le même résultat** (exigence
  BC-22) — tous les read models ci-dessous sont calculés à la volée par des
  agrégations simples scopées `company_id`, jamais par des jointures
  profondes transactionnelles.

## Convention

Chaque ligne suit le schéma :

```text
[Read model / export]
  Sources   : tables tenant lues (scope obligatoire)
  Agrégat   : opérations (count / sum / group) et filtres
  Endpoint  : route API + contrôleur + RBAC
  Consom.   : consommateurs (dashboard web, apps, exports)
  Fraîcheur : à la volée (déterministe) — aucun cache mutable
  Masquage  : PII / injection CSV
```

## 1. Tableau de bord comptable (`AccountingDashboardService`)

| Étape | Détail |
|---|---|
| **Sources** | `accounting_documents` (factures/dépenses), `accounting_payments` (encaissements) — scopées `company_id` (BelongsToCompany, fail-closed #3727) |
| **Agrégat** | `invoices` : count + `SUM(total_ttc)` des documents client `sent/partially_paid/paid/overdue` émis dans la période ; `collections` : count + `SUM(amount)` des paiements reçus dans la période ; `expenses` : count + `SUM(total_ttc)` des documents fournisseur ; `outstanding` : documents non soldés (`total_ttc > paid_amount`) + aging par retard (buckets 0_30/31_60/61_90/90_plus) |
| **Endpoint** | `GET /api/v1/accounting/dashboard` → `AccountingDashboardController::show` (RBAC `api.manager:comptable,principal`), Request `AccountingDashboardRequest` (période validée), Resource `DashboardSummaryResource` |
| **Consom.** | Dashboard comptable (front admin), export CSV impayés |
| **Fraîcheur** | À la volée — recompute = même requête → même résultat (test golden `GoldenDashboardRecomputeTest`) |
| **Masquage** | `CsvCellSanitizer` sur l'export (neutralise formules `=`, `+`, `-`, `@`) |

## 2. Export CSV des impayés (aging)

| Étape | Détail |
|---|---|
| **Sources** | `accounting_documents` (+ `accounting_contacts` via `contact:id,company_id,name`) — scopées `company_id` |
| **Agrégat** | Liste bornée (`limit 100`), tri `due_date`, montants `total_ttc`, `paid_amount`, `due_amount` recalculés |
| **Endpoint** | `GET /api/v1/accounting/dashboard/export` → `AccountingDashboardController::export` |
| **Consom.** | Téléchargement CSV (comptable/principal) |
| **Fraîcheur** | À la volée |
| **Masquage** | `CsvCellSanitizer` sur chaque cellule (`CsvCellSanitizerTest`) |

## 3. Journal comptable + export FEC / CSV expert

| Étape | Détail |
|---|---|
| **Sources** | `accounting_journal_entries` (+ références document) — scopées `company_id` |
| **Agrégat** | Écritures par période, pagination bornée, export FEC DGFiP (13 colonnes) / CSV expert |
| **Endpoint** | `GET /api/v1/accounting/journal`, `GET /api/v1/accounting/journal/export-fec`, `GET /api/v1/accounting/journal/export.csv` → `AccountingJournalController` |
| **Consom.** | Comptable/principal, export comptable |
| **Fraîcheur** | À la volée ; période clôturée = journal figé (BC-08) |
| **Masquage** | Exports limités aux données tenant, pas de PII brute dans les libellés |

## 4. Rapports de présence / flotte / pilote

| Étape | Détail |
|---|---|
| **Sources** | `attendance_logs` (BC-05), véhicules/flotte (BC-18), agrégats pilotes — scopées `company_id` |
| **Agrégat** | `AttendanceReportService`, `FleetReportRequest`, `PilotReportCommand`, `PilotKpiReportCommand` — count/sum/moyennes par période |
| **Endpoint** | Routes versionnées des modules respectifs (RBAC manager) |
| **Consom.** | Rapports manager, KPIs pilotes |
| **Fraîcheur** | À la volée |
| **Masquage** | Pas de PII superflue dans les KPIs agrégés |

## 5. Exports bancaires (asynchrones, BC-07 → reporting)

| Étape | Détail |
|---|---|
| **Sources** | `bank_exports`, `payroll_runs`, `pay_slips` (BC-07) — job `GenerateBankExportJob` tenant-scoped |
| **Agrégat** | Génération du fichier (SEPA/CCP/CPA/BNA/CSV) hors cycle HTTP, statuts `pending → generating → generated/failed`, retry borné (`tries 3`, timeout 120 s) |
| **Endpoint** | `POST /api/v1/bank-exports` (dispatch) → statut via `BankExportController` |
| **Consom.** | Bordereaux bancaires (paie) |
| **Fraîcheur** | Asynchrone ; l'idempotence est portée par la machine à états `BankExport` |
| **Masquage** | RIB chiffré (colonne `reference`), fichiers sur stockage tenant-isolé |

## 6. Métriques plateforme (ops)

| Étape | Détail |
|---|---|
| **Sources** | Agrégats plateforme (compteurs observabilité, files, communication) — hors périmètre tenant |
| **Agrégat** | `PlatformMetricsOverviewController`, `MetricsController`, `CommunicationAnalyticsController`, `QueueObservabilityController` |
| **Endpoint** | Routes `/admin/*` et `/api/v1/metrics*` (RBAC super-admin / ops) |
| **Consom.** | Cockpit admin, alerting |
| **Fraîcheur** | À la volée |
| **Masquage** | Logs redacted (PII hors logs structurés) |

## Politique de fraîcheur et de snapshots

**Décision (2026-08-30, DEP-BC22) :** les read models de reporting sont
**calculés à la volée** avec des agrégations simples scopées. Aucun snapshot
matérialisé n'est introduit tant que les volumes restent dans le budget p95
du registre `dev-hub/tools/performance-budgets.json`.

Un snapshot ne sera introduit que si **toutes** les conditions suivantes
sont réunies (décision ADR, PR dédiée) :

1. un endpoint de reporting dépasse son budget p95 en CI (k6, `k6-load-smoke.yml`) ;
2. le recompute à la volée consomme plus de X % des E/S de la base en production ;
3. le snapshot est **versionné et idempotent** : table dédiée ou vue
   matérialisée avec `refresh` rejouable, clé `(company_id, période, version)`,
   et « deux recalculs produisent le même résultat » vérifié par test golden ;
4. la fraîcheur est **horodatée et documentée** (champ `refreshed_at` exposé
   à l'API) pour que le consommateur connaisse l'âge de la donnée.

## Garde-fous

- Toute nouvelle agrégation de reporting doit être scopée `company_id`
  (aucune table tenant lue sans scope — fail-closed #3727).
- Pas de jointures profondes transactionnelles dans les read models : les
  agrégations utilisent count/sum sur modèles tenant + relations simples.
- Tout export CSV passe par `CsvCellSanitizer` (injection neutralisée).
- Les endpoints de reporting sont inscrits au registre des budgets p95
  (`performance-budgets.json`) — ajout d'un endpoint = mise à jour du registre.
