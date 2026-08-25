# Benchmark de charge — module Comptabilité

> Référentiel : `docs/accounting/BENCHMARK.md` — protocole F-12 (issue #5275),
> calqué sur `docs/payroll/BENCHMARK.md` (payroll:benchmark).

## Objectif

Garantir que le module Comptabilité tient la charge cible :
**10 000 documents par entreprise, recherche < 200 ms, 0 N+1 sur les chemins
de lecture canoniques**, exports PDF en queue (job asynchrone, hors chemin
synchrone).

## Protocole

```bash
cd api
php artisan accounting:benchmark --documents=10000
```

Ce que fait la commande (`AccountingBenchmark`, seed `AccountingBenchmarkSeeder`) :

1. **Seed** : entreprise dédiée `benchmark-accounting-dz`, `N` documents
   réalistes (statuts étalés, échéances sur ±1 an, 3 lignes chacun,
   paiements pour les documents soldés) — inserts groupés par paquets de
   1 000 (mémoire bornée).
2. **Mesures** :
   - liste par statut, paginée (50/page), **eager loading**
     `contact + lines + payments` → compteur de requêtes SQL (barrière N+1 :
     le nombre de requêtes doit rester **≤ 5 quel que soit N**) ;
   - requête « relances » (statuts émis + `due_date ≤ J-7` + non soldé) →
     nombre d'éligibles + temps ;
   - agrégation par mois d'émission (profil « journal/rapport ») → mois
     couverts + temps.

## Cibles (DoD #5275)

| Métrique | Cible |
|---|---|
| Recherche/liste sur 10 000 documents | < 200 ms |
| Requêtes SQL de la liste paginée (eager) | constantes (≤ 5), indépendantes de N |
| N+1 | 0 sur `contact`, `lines`, `payments` |
| Génération PDF | queue asynchrone uniquement (jamais synchrone en API) |
| Seed benchmark | < 60 s pour 10 000 documents |

## Index en place (issue #5275)

| Table | Index | Sert |
|---|---|---|
| `accounting_documents` | `(company_id, type, status)` | listes par type/statut (socle #5221) |
| `accounting_documents` | `(company_id, status, due_date)` | relances, échéances |
| `accounting_documents` | `(company_id, issue_date)` | journaux/rapports par période |
| `accounting_documents` | `(company_id, number)` unique | numérotation |
| `accounting_payments` | `(company_id, document_id, status)` | trésorerie filtrée |
| `accounting_journal_entries` | `(company_id, period)` | journal par période (#5234) |

## Résultats de référence

> À remplir après chaque campagne de mesure (date, environnement,
> `--documents`, résultats).

| Date | Env | Documents | Liste (temps / requêtes) | Relances (temps / éligibles) | Agrégation (temps / mois) |
|---|---|---|---|---|---|
| 2026-08-24 | local PHP 8.4 + PostgreSQL 16 | 10 000 | **0.01 s / 5** | 0.00 s / 1 763 | 0.01 s / 13 |
| 2026-08-24 | local PHP 8.4 + PostgreSQL 16 | 300 | 0.01 s / 5 | 0.00 s / 51 | 0.00 s / 13 |

## Garde CI (audit N+1)

`AccountingPerformanceTest` (suite `tests/Feature/Accounting`) :

- `test_canonical_list_has_no_n_plus_one` : 5 documents × 3 lignes + paiements
  → le chargement eager (contact + lignes + paiements) doit tenir en ≤ 5
  requêtes (0 N+1) ;
- `test_reminder_query_uses_indexed_path_and_returns_eligible_only` : filtre
  relances indexé, seuls les éligibles sortent ;
- `test_period_aggregation_is_supported_by_indexes` : agrégation mensuelle
  correcte sur l'index `(company_id, issue_date)`.
