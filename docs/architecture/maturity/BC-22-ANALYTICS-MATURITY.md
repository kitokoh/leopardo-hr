# BC-22 — Analytics & Reporting — Rapport de maturité (DEP-BC22)

- **Statut :** PARTIAL → corrections livrées (#5898)
- **Date :** 2026-08-29
- **Agent propriétaire :** 22 (Analytics & Reporting)
- **Référentiel :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` §BC-22
- **Périmètre :** read models, agrégats, exports, data quality

## Cartographie de l'existant

| Brique | Composant |
|---|---|
| Dashboards | `AccountingDashboardService` (factures, encaissements, impayés/aging, dépenses — lecture seule, scopé `company_id`), `AccountingDashboardController`, `MyDashboardController`, `DashboardSummaryResource` |
| Rapports | `AccountingReportController`, `AttendanceReportService`, `FleetReportRequest`, `PilotReportCommand`, `PilotKpiReportCommand` |
| Exports | `JournalCsvExporter`, `FecExporter`, `GenerateBankExportJob` (async), `JournalCsvExporter`, CSV outstanding (sanitisé `CsvCellSanitizer`) |
| Platform metrics | `PlatformMetricsOverviewController`, `MetricsController`, `CommunicationAnalyticsController`, `QueueObservabilityController` |
| Data quality | `DataAccessAuditLogger`, `AuditSensitiveReportCommand`, `PayrollCalculationAuditRecorder` |

## Audit des douze dimensions

| Dim | Statut | Preuve / Lacune |
|---|---|---|
| D1 Domaine | **PARTIAL** | Vocabulaire de reporting dispersé (compta/attendance/fleet) — pas de glossaire analytics unifié |
| D2 Données | **PRESENT** | Agrégations sur modèles tenant existants, index présents |
| D3 Tenant | **PRESENT** | Toutes les requêtes scopées `company_id` (fail-closed #3727), test d'isolation tenant |
| D4 API | **PRESENT** | Routes versionnées, Requests (période validée), OpenAPI |
| D5 Autorisation | **PRESENT** | RBAC (principal/manager), tests 401/403 |
| D6 Transactions | **PRESENT** | Read models en lecture seule — aucun impact transactionnel |
| D7 Asynchronisme | **PARTIAL** | Exports lourds en jobs (`GenerateBankExportJob`) ; FEC/Journal sync |
| D8 Sécurité | **PRESENT** | `CsvCellSanitizer` (injection CSV), pas de PII dans les logs, exports scopés |
| D9 Frontends | **PRESENT** | Dashboards admin/manager, états UI |
| D10 Performance | **PARTIAL→CORRIGÉ** | Pagination bornée ; **invariant verrouillé : read model déterministe + léger (pas de jointures profondes transactionnelles)** |
| D11 Exploitation | **PARTIAL** | `PilotReportCommand`/`PilotKpiReportCommand` ; pas de lineage documenté |
| D12 Produit | **PARTIAL** | KPIs pilotes documentés ; recette reporting en cours |

## Corrections livrées dans cette PR

1. **Invariant « deux recalculs produisent le même résultat » (D10)** —
   `GoldenDashboardRecomputeTest` :
   - jeu de données déterministe, montants **calculés à la main** (2 factures
     client 14 280 TTC, 1 fournisseur 1 190, 2 encaissements 14 280) ;
   - deux appels successifs de `AccountingDashboardService::summary()` →
     résultats **identiques** ;
   - l'ajout de données chez un AUTRE tenant ne modifie jamais le read model
     du tenant courant (isolation + déterminisme).
   Le read model n'utilise que des agrégations simples scopées (count/sum)
   sur les modèles tenant — pas de jointures profondes transactionnelles.

## Sortie exigée par le backlog

- [x] Deux recalculs produisent le même résultat (test golden)
- [x] Les dashboards n'utilisent pas de jointures profondes transactionnelles (agrégations simples scopées)
- [x] Un manager ne voit que les données autorisées (RBAC testé)

## Reste à faire (hors périmètre de cette PR courte)

- Lineage des read models (source → agrégat → export)
- Fraîcheur/raccourci (snapshots horodatés si volumes)
- Budgets p95 sur les endpoints de reporting
