# Glossaire unifié Analytics & Reporting

> **BC-22 — D01 Domaine (issue #6245).** Lexique commun des read models de
> reporting, référencé par le lineage des read models
> (`docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md`), le runbook
> (`docs/ops/RUNBOOK_REPORTING_ANALYTICS.md`) et le rapport de maturité BC-22
> (`docs/architecture/maturity/BC-22-ANALYTICS-MATURITY.md`).

Ce glossaire définit une fois pour toutes le vocabulaire du reporting Leopardo.
Chaque terme a : **définition**, **source** (table / BC propriétaire), **exemple**
et **invariant** éventuel. Aucune redéfinition contradictoire n'est tolérée :
en cas de doute, ce document fait foi, puis le lineage fait foi sur la
provenance des données.

---

## A — Concepts génériques

### Read model
- **Définition** : projection calculée (lecture seule) qui expose une vue
  agrégée du domaine à un écran ou un export — jamais une table écrite par le
  flux transactionnel.
- **Source** : agrégations sur les tables tenant du BC propriétaire (ex.
  `accounting_documents` pour le dashboard comptable).
- **Exemple** : `AccountingDashboardService::summary()` (factures émises,
  encaissements, dépenses, impayés).
- **Invariant** : tout read model est scopé `company_id` (fail-closed #3727) ;
  un read model est **déterministe** : deux calculs sur les mêmes données
  produisent exactement le même résultat (testé par `GoldenDashboardRecomputeTest`).

### Agrégat
- **Définition** : opération de synthèse (count / sum / group) appliquée aux
  données source d'un read model.
- **Source** : requêtes Eloquent scopées du BC propriétaire.
- **Exemple** : `invoices.count` + `SUM(total_ttc)` des factures émises sur la
  période.
- **Invariant** : pas de jointure profonde transactionnelle dans un read model
  (le reporting ne ralentit jamais les transactions).

### Période (de reporting)
- **Définition** : fenêtre `[from, to]` (dates inclusives) sur laquelle les
  agrégats sont calculés.
- **Source** : paramètres validés de la requête (`AccountingDashboardRequest`),
  défauts = début du mois → aujourd'hui.
- **Exemple** : `period: {from: "2026-08-01", to: "2026-08-30"}`.
- **Invariant** : `from ≤ to` ; format ISO `YYYY-MM-DD`.

### Fraîcheur
- **Définition** : âge de la donnée lue par un consommateur, i.e. le délai
  entre la dernière écriture transactionnelle prise en compte et l'instant de
  lecture.
- **Source** : politique du read model (cf. lineage, colonne « Fraîcheur »).
- **Exemple** : read model calculé à la volée = fraîcheur immédiate
  (déterministe) ; snapshot = fraîcheur bornée par `refreshed_at`.
- **Invariant** : la fraîcheur est **explicitement exposée** au consommateur
  (`refreshed_at` pour un snapshot ; « à la volée » sinon) — jamais implicite.

### Recompute
- **Définition** : recalcul d'un read model (ou d'un snapshot) à partir des
  données source, sans état préexistant.
- **Source** : service du read model (ex. `AccountingDashboardService`) ou job
  de recompute snapshot.
- **Exemple** : `accounting:reporting-snapshot {company}` recalcule le snapshot
  du dashboard.
- **Invariant** : un recompute est **idempotent** — deux recomputes successifs
  produisent le même résultat (et, pour un snapshot, la même version si le
  contenu n'a pas changé).

### Snapshot
- **Définition** : matérialisation versionnée d'un read model pour une période
  donnée, recalculable à la demande et horodatée (`refreshed_at`).
- **Source** : table `accounting_reporting_snapshots` (BC-22), alimentée par le
  job `RecomputeAccountingReportingSnapshotJob`.
- **Exemple** : snapshot `accounting_dashboard` pour `[2026-08-01, 2026-08-30]`,
  version 3, `refreshed_at` = dernière exécution.
- **Invariant** : clé de version `(company_id, report, période, version)` ;
  deux recomputes à contenu identique → même version ; stratégie d'activation
  documentée dans `docs/architecture/ANALYTICS_SNAPSHOTS.md` (déclencheur :
  dépassement du budget p95 du registre).

### Lineage
- **Définition** : cartographie « source → agrégat → endpoint → export » d'un
  read model — d'où vient chaque chiffre et qui le consomme.
- **Source** : `docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md` (BC-22).
- **Exemple** : impayés ← `accounting_documents` (statuts
  `sent/partially_paid/overdue`, `total_ttc > paid_amount`) → endpoint
  `/accounting/dashboard` → export CSV impayés.
- **Invariant** : tout nouveau read model ou modification de source met à jour
  le lineage dans la même PR.

### Budget p95
- **Définition** : cible de latence (95ᵉ percentile, ms) d'un endpoint critique,
  enregistrée dans le registre de performance.
- **Source** : `dev-hub/tools/performance-budgets.json` (MAT-014, #5872).
- **Exemple** : `GET /api/v1/accounting/dashboard` → `p95_ms: 300`.
- **Invariant** : le dépassement d'un budget p95 sur un endpoint de reporting
  est le **déclencheur** de l'introduction d'un snapshot (BC-22-D10) ; le
  budget est mis à jour quand la stratégie change.

---

## B — Termes métier du reporting comptable

### Facture émise
- **Définition** : document client de type facture (ou mixte) dans un statut
  émis (`sent`, `partially_paid`, `paid`, `overdue`), émis dans la période.
- **Source** : `accounting_documents` (BC-08 Accounting) — hors brouillons et
  annulés, contact `customer`/`both`.
- **Exemple** : `invoices: {count: 5, total_ttc: 1 240 500.00}`.
- **Invariant** : une facture n'est **jamais** `paid` sans paiement enregistré
  couvrant le total (`DocumentWorkflowService` #5223).

### Encaissement
- **Définition** : paiement reçu sur un document, comptabilisé sur sa date de
  réception (`received_at`).
- **Source** : `accounting_payments` (BC-08) — statuts `recorded`/`matched`.
- **Exemple** : `collections: {count: 2, total: 305 000.00}`.
- **Invariant** : le cumul des encaissements d'un document ne dépasse jamais
  son total TTC ; `paid_amount` est le champ dénormalisé maintenu par le
  workflow.

### Impayé
- **Définition** : document client non soldé — `total_ttc > paid_amount` — dans
  un statut `sent`, `partially_paid` ou `overdue`.
- **Source** : `accounting_documents` (BC-08).
- **Exemple** : une facture de 120 000 DZD avec 40 000 DZD encaissés.
- **Invariant** : **la définition canonique d'un impayé est
  `total_ttc > paid_amount`** (verrouillée par test golden) — ne pas la
  redéfinir localement.

### Aging (vieillissement)
- **Définition** : répartition des impayés par retard (jours depuis
  `due_date`), en buckets `0_30`, `31_60`, `61_90`, `90_plus`.
- **Source** : calcul du read model dashboard (BC-22) sur `accounting_documents`.
- **Exemple** : `aging: {0_30: 2, 31_60: 1, 61_90: 0, 90_plus: 1}`.
- **Invariant** : un document non échu (`due_date` future) n'apparaît dans
  aucun bucket d'aging.

### Dépense (fournisseur)
- **Définition** : document fournisseur émis dans la période (statuts émis),
  hors brouillons/annulés.
- **Source** : `accounting_documents` (BC-08) — contact `supplier`/`both`.
- **Exemple** : `expenses: {count: 1, total_ttc: 89 000.00}`.

### Export
- **Définition** : sortie fichier (CSV, FEC…) d'un read model, téléchargeable
  et sanitizée.
- **Source** : exports des contrôleurs du BC propriétaire
  (ex. `AccountingDashboardController::export`).
- **Exemple** : export CSV des impayés (`accounting-dashboard-outstanding-*.csv`).
- **Invariant** : toute cellule exportée passe par `CsvCellSanitizer`
  (neutralisation des formules `=`, `+`, `-`, `@` — injection CSV) ; l'export
  est borné (`limit 100` pour les impayés).

---

## Alignement du vocabulaire API

Les noms de champs exposés par les Resources doivent rester alignés sur ce
glossaire. État actuel (dashboard comptable, `GET /accounting/dashboard`) :

| Glossaire | Champ API |
|---|---|
| Facture émise | `data.invoices.count`, `data.invoices.total_ttc` |
| Encaissement | `data.collections.count`, `data.collections.total` |
| Dépense | `data.expenses.count`, `data.expenses.total_ttc` |
| Impayé | `data.outstanding.list[]` (items `total_ttc`, `paid_amount`, `due_amount`, `days_late`, `status`) |
| Aging | `data.outstanding.aging` (clés `0_30`, `31_60`, `61_90`, `90_plus`) |
| Période | `data.period.from`, `data.period.to` |
| Fraîcheur | `data.snapshot` (`source: "live"|"snapshot"`, `refreshed_at`, `version`) — BC-22-D10 |

**Règle** : introduire un nouveau champ de reporting = ajouter le terme au
glossaire + le mapper au lineage + couvrir le champ par OpenAPI dans la même PR.
