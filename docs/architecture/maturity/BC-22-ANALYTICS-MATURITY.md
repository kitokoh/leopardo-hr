# Rapport de maturité — BC-22 Analytics & Reporting

> **DEP-BC22 (issue #5898)** — Deep maturity, BC-22 Analytics & Reporting.
> Audité et corrigé le 2026-08-30. Agent propriétaire : 22 (Analytics & Reporting).
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Lineage : `docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md`.
> Runbook : `docs/ops/RUNBOOK_REPORTING_ANALYTICS.md`.
> Budgets p95 : `dev-hub/tools/performance-budgets.json` (§critical_endpoints).

## Statut

**HARDENING** — corrections livrées (read models déterministes et isolés,
budgets p95 verrouillés, lineage et runbook documentés). Il reste des
recommandations non bloquantes (PR futures, §Recommandations).

## Périmètre

Read models, agrégats, exports et data quality : dashboards comptables,
rapports présence/flotte/pilote, exports CSV/FEC et bancaires asynchrones,
métriques plateforme. Le reporting est **lecture seule** : il ne ralentit
jamais les transactions et ne lit aucune table tenant sans scope
(fail-closed #3727).

## Cartographie de l'existant

| Brique | Composants |
|---|---|
| Dashboards | `AccountingDashboardService` (factures, encaissements, impayés/aging, dépenses — lecture seule, scopé `company_id`), `AccountingDashboardController`, `MyDashboardController`, `DashboardSummaryResource` |
| Rapports | `AccountingReportController`, `AttendanceReportService`, `FleetReportRequest`, `PilotReportCommand`, `PilotKpiReportCommand` |
| Exports | `JournalCsvExporter`, `FecExporter`, `GenerateBankExportJob` (async, tenant-scoped, retry borné), export CSV impayés sanitisé (`CsvCellSanitizer`) |
| Platform metrics | `PlatformMetricsOverviewController`, `MetricsController`, `CommunicationAnalyticsController`, `QueueObservabilityController` |
| Data quality | `DataAccessAuditLogger`, `AuditSensitiveReportCommand`, `PayrollCalculationAuditRecorder` |

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL → 🟢 CORRIGÉ | Vocabulaire + **lineage source → agrégat → endpoint → export** documenté (`ANALYTICS_READ_MODEL_LINEAGE.md`) ; invariants verrouillés par `GoldenDashboardRecomputeTest`. |
| D2 | Données | 🟢 PRÉSENT | Agrégations sur modèles tenant existants ; index reporting présents (`accounting_documents_company_issue_index`, `accounting_documents_company_status_due_index`, `accounting_payments_company_document_status_index`, migration 000005) et inscrits au registre des index requis. |
| D3 | Tenant | 🟢 PRÉSENT | Toutes les requêtes scopées `company_id` (fail-closed #3727) ; test d'isolation tenant (dashboards + `GoldenDashboardRecomputeTest`). |
| D4 | API | 🟢 PRÉSENT | Routes versionnées (`/api/v1/accounting/dashboard*`), Request `AccountingDashboardRequest` (période validée), OpenAPI couvert (`openapi.yaml` §23073). |
| D5 | Autorisation | 🟢 PRÉSENT | RBAC `api.manager:comptable,principal` ; tests 403 employé/marketing + accès principal + isolation tenant (`AccountingDashboardTest`). |
| D6 | Transactions | 🟢 PRÉSENT | Read models en lecture seule — aucun impact transactionnel, aucun verrou. |
| D7 | Asynchronisme | 🟡 PARTIEL | Exports lourds en jobs (`GenerateBankExportJob` : tenant-scoped, `tries 3`, timeout, statuts pending→generating→generated/failed, testé) ; FEC/Journal sync (bornés). |
| D8 | Sécurité | 🟢 PRÉSENT | `CsvCellSanitizer` (injection CSV neutralisée, testé), pas de PII dans les logs, exports scopés, masquage documenté dans le lineage. |
| D9 | Frontends | 🟢 PRÉSENT | Dashboards admin/manager, états UI, permissions UI non autoritaires. |
| D10 | Performance | 🟡 PARTIEL → 🟢 CORRIGÉ | Pagination/borne (`limit 100` impayés) ; **budgets p95 verrouillés** pour les endpoints reporting (`performance-budgets.json` : dashboard 300 ms, export CSV 700 ms, FEC 800 ms) ; read models en agrégations simples scopées (pas de jointures profondes transactionnelles), déterminisme testé. |
| D11 | Exploitation | 🟡 PARTIEL → 🟢 CORRIGÉ | **Runbook** `RUNBOOK_REPORTING_ANALYTICS.md` (symptômes → diagnostic → action → rollback + alertes) ; `PilotReportCommand`/`PilotKpiReportCommand` ; observabilité (logs corrélés, `QueueObservabilityController`). |
| D12 | Produit | 🟡 PARTIEL | KPIs pilotes documentés ; **invariant data quality verrouillé** (impayés = `total_ttc > paid_amount`, `paid_amount` dénormalisé maintenu par `DocumentWorkflowService`) ; recette reporting en cours — golden journey UI end-to-end à formaliser (recommandation 3). |

## Corrections livrées dans cette PR

1. **Invariant « deux recalculs produisent le même résultat » (D10, D3)** —
   `GoldenDashboardRecomputeTest` (2 tests, 14 assertions) :
   - jeu de données déterministe, montants **calculés à la main** (2 factures
     client 14 280 TTC, 1 fournisseur 1 190, 2 encaissements 14 280, 1 impayé
     1 190) ;
   - deux appels successifs de `AccountingDashboardService::summary()` →
     résultats **identiques** ;
   - l'ajout de données chez un AUTRE tenant ne modifie jamais le read model
     du tenant courant (isolation + déterminisme) ;
   - **invariant data quality** : les encaissements maintiennent `paid_amount`
     sur le document (contrat `DocumentWorkflowService`) — le read model des
     impayés lit `total_ttc > paid_amount` (documenté dans le lineage).
   Le read model n'utilise que des agrégations simples scopées (count/sum)
   sur les modèles tenant — pas de jointures profondes transactionnelles.
2. **Lineage des read models (D1/D11)** — `docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md` :
   source → agrégat → endpoint → export pour chaque brique, politique de
   fraîcheur (recompute à la volée) et conditions d'introduction d'un
   snapshot versionné (ADR, PR dédiée).
3. **Budgets p95 (D10)** — endpoints de reporting inscrits au registre
   `dev-hub/tools/performance-budgets.json` + index reporting dans
   §required_indexes (garde CI `check-performance-budgets.sh`).
4. **Runbook (D11)** — `docs/ops/RUNBOOK_REPORTING_ANALYTICS.md` :
   diagnostic dashboard lent, non-déterminisme, exports CSV/FEC, jobs
   d'export bancaire, métriques plateforme, alertes et preuves CI.

## Sortie exigée par le backlog (BC-22)

- [x] Deux recalculs produisent le même résultat (test golden)
- [x] Les dashboards n'utilisent pas de jointures profondes transactionnelles (agrégations simples scopées)
- [x] Un manager ne voit que les données autorisées (RBAC testé — `AccountingDashboardTest`)
- [x] Lineage documenté (source → agrégat → export)
- [x] Budgets p95 sur les endpoints de reporting
- [x] Runbook d'exploitation livré

## Vérification CI (preuve)

```bash
php artisan test --filter="GoldenDashboardRecomputeTest|AccountingDashboardTest"   # invariants + RBAC + isolation
php artisan test --filter="CsvCellSanitizerTest"                                    # masquage CSV
php artisan test --filter="GenerateBankExportJobTest"                               # export async tenant-scoped
bash dev-hub/tools/check-performance-budgets.sh api                                 # registre p95 cohérent (exit 0)
```

## Recommandations (PR futures, non bloquantes)

1. **Snapshots horodatés** (D2/D10) : si un endpoint dépasse son budget p95
   en charge réelle (k6), introduire un snapshot versionné et idempotent
   (clé `(company_id, période, version)`, `refreshed_at` exposé) — politique
   détaillée dans le lineage.
2. **Golden journey UI end-to-end** (D12) : seed pilote synthétique + test
   E2E « ouvrir le dashboard comptable → lire les agrégats → exporter les
   impayés » sur données synthétiques (aucune donnée réelle).
3. **Glossaire analytics unifié** (D1) : regrouper les termes de reporting
   (facture émise, encaissement, impayé, aging, read model, fraîcheur) dans
   un lexique commun référencé par les modules.

## Non-régression

Aucun code de production modifié dans cette PR : rapport, invariants
(tests), lineage, runbook et registre de budgets uniquement.
