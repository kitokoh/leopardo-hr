# Rapport de maturité — BC-08 ACCOUNTING

> **DEP-BC08 (issue #5884)** — Deep maturity, BC-08 Accounting.
> Audité le 2026-08-30 (main `62c00afef`). Agent propriétaire : 08.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-08, `active`).

## Périmètre

Comptabilité client : plan comptable, journaux, documents, lettrage, TVA,
clôtures, paiements, contacts, rapprochement bancaire, partage sécurisé.
`api/app/Modules/Accounting` (13 modèles, 24 services, 15+ contrôleurs),
routes `/api/v1/accounting*`, migrations 5422/5424/5425 (chart, fiscal years,
lettering, bank statements), exports CSV (JournalCsvExporter).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Modèles DDD : AccountingDocument (+Line), AccountingJournalEntry, AccountingChartAccount, AccountingFiscalYear, AccountingClosedPeriod, AccountingPayment, BankStatementLine, AccountingContact ; vocabulaire comptable documenté. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (chart + fiscal years 5422, lettering 5425, bank statements 5435), index dédiés, clôtures de période protégées, garde schéma verte. |
| D3 | Tenant | 🟢 PRÉSENT | Scopes `BelongsToCompany` systématiques, cross-tenant 404 testé, aucun accès direct aux tables des autres modules (contrats par événements, ex. FuelStation FUEL-015). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs Document/Journal/Chart/Contact/Payment/Dashboard/Audit + Requests validées ; OpenAPI couvert ; exports CSV neutralisés (CsvCellSanitizer). |
| D5 | Autorisation | 🟢 PRÉSENT | AccountingPolicy + rôles (comptable/manager), partage de documents par lien signé (AccountingDocumentShare, purge `accounting:purge-expired-shares`), tests 401/403/404. |
| D6 | Transactions | 🟢 PRÉSENT | Documents comptables équilibrés (débit=crédit) validés en transaction, clôtures de période (AccountingClosedPeriod) irréversibles, lettrage auditée, écritures paie dans la même transaction que le run. |
| D7 | Asynchronisme | 🟡 PARTIEL | Partage signé + purge planifiée ; imports/export sync bornés ; événements d'outbox transverse en cours de généralisation (BC-14). |
| D8 | Sécurité | 🟢 PRÉSENT | Parts signés à durée de vie bornée, exports audités (DataAccessAuditLogger + export_history), PII limitée (contacts chiffrés si nécessaire), aucun secret dans les fixtures. |
| D9 | Frontend | 🟢 PRÉSENT | Admin dashboard (écrans comptables, lettrage, clôtures), apps mobile (validation), i18n ×4. |
| D10 | Performance | 🟡 PARTIEL | Index dédiés + pagination ; budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks ops, commandes (purge shares, réconciliation), logs structurés, audit trail comptable (AccountingAuditController). |
| D12 | Produit | 🟡 PARTIEL | Golden journey comptable (document → lettrage → clôture → journal) partiellement couvert ; seed pilote synthétique absent. |

## Vérification locale (preuve)

```
./vendor/bin/pest tests/Feature/Accounting
→ 24 fichiers de tests verts (documents, journal, chart, lettrage, clôtures, exports)
```
Tests clés : `AccountingDocumentFlowTest`, `AccountingLetteringTest`,
`AccountingClosedPeriodTest`, `PayrollAccountingExportTest` (intégration
paie → comptabilité).

## Recommandations (PR futures, non bloquantes)

1. **Golden journey comptable** (D12) : test E2E facture fournisseur → lettrage
   → clôture de période → grand-livre, avec seed pilote déterministe.
2. **Événements d'outbox** (D7) : publier `accounting.document.posted.v1` /
   `accounting.period.closed.v1` dans le runtime outbox (BC-14) pour les
   intégrations (FuelStation FUEL-015 consomme déjà l'inverse).
3. **Budgets performance** (D10) : verrouiller p95/p99 sur les journaux
   volumineux (AccountingJournalEntry) une fois MAT-014 mergé.
4. **Rapprochement bancaire** (D2) : compléter le matching automatique
   (règles de similarité bornées) en PR courte, avec tests d'ambiguïté.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
