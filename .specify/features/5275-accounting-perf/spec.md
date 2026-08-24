# Feature Specification: Module Comptabilité — Perf/scale (Closes #5275)

**Feature Branch**: `mod/accounting/5275-accounting-perf`
**Issue**: #5275 (P2, backend, perf)
**Created**: 2026-08-24
**Status**: Implementation

## Contexte

Le module Comptabilité doit tenir la charge : milliers de documents par
entreprise, listes paginées, requête relances, agrégations de journal. Cette
issue livre l'audit de performance : **index manquants**, **barrière N+1** sur
les chemins de lecture canoniques, **protocole de mesure F-12** (calqué sur
`payroll:benchmark`) et **métriques documentées**.

## User Stories

### US1 — Les workloads du module sont indexés (P1)

**Independent Test**: migration additive → `accounting_documents
(company_id, status, due_date)`, `(company_id, issue_date)` et
`accounting_payments (company_id, document_id, status)` existent (idempotent).

### US2 — Zéro N+1 sur la liste canonique (P1)

**Independent Test**: `AccountingPerformanceTest` — 5 documents × 3 lignes +
paiements : le chargement eager (contact + lignes + paiements) tient en
≤ 5 requêtes, quel que soit le nombre de documents.

### US3 — Protocole de mesure documenté (P2)

**Independent Test**: `artisan accounting:benchmark --documents=10000` se déroule
sans erreur et imprime liste (temps + requêtes), relances (éligibles) et
agrégation (mois). Cible : recherche < 200 ms sur 10 000 documents.

## Requirements

- **FR-001**: migration tenant additive `2026_08_23_000005_add_accounting_performance_indexes.php`
  (3 index composés, gardes `hasIndex`, `down()` symétrique).
- **FR-002**: `AccountingBenchmarkSeeder` (Database/Seeders) : entreprise dédiée
  `benchmark-accounting-dz`, N documents réalistes (statuts/échéances étalés,
  3 lignes, paiements), inserts groupés par 1000 — **garde anti-réseed**
  (entreprise existante → retour immédiat).
- **FR-003**: commande `accounting:benchmark {--documents=10000}` : seed + 3 mesures
  (liste paginée eager avec compteur de requêtes / relances J+7 / agrégation
  mensuelle) + tableau de sortie.
- **FR-004**: `AccountingPerformanceTest` (3 tests) : N+1, chemin relances indexé,
  agrégation par période.
- **FR-005**: `docs/accounting/BENCHMARK.md` : protocole, cibles DoD, index en
  place, tableau de référence rempli, garde CI.

## Hors périmètre

- Pagination des endpoints (`/accounting/journal`, `/accounting/payments`) :
  portée par les PR #5363/#5365 (le pattern `paginate()` est démontré dans le
  benchmark).
- Perf PDF (dompdf) : génération asynchrone déjà en queue (#5224) — hors scope.
- Charge réelle multi-tenant : benchmark mono-entreprise (slug dédié).

## Success Criteria

- 3 index composés en place (migration additive, idempotente) ;
- barrière N+1 : ≤ 5 requêtes constantes pour la liste canonique ;
- benchmark 10 000 documents : **0.01 s** sur la liste paginée (< 200 ms cible) —
  métriques réelles documentées dans `docs/accounting/BENCHMARK.md` ;
- tests verts + PHPStan Strict 0 erreur + Pint PASS ; `Closes #5275` dans la PR ;
  CHANGELOG.
