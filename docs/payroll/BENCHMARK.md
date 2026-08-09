# Benchmark clôture mensuelle — F-12 (#1542/#1594)

Protocole de mesure de la clôture de paie mensuelle (calculate → validate-rh
→ lock) sur un jeu de données DZ réaliste.

## Prérequis

- API bootable (docker compose up, ou env local PG + Redis).
- `composer install` fait.

## Lancer

```bash
# Jeu réduit (rapide) :
dev-hub/tools/payroll-benchmark.sh --employees=1000 --step=all

# Cible F-12 :
dev-hub/tools/payroll-benchmark.sh --employees=10000 --step=all
```

Équivalents artisan :

```bash
php artisan payroll:benchmark --employees=10000 --step=calculate
php artisan payroll:benchmark --employees=10000 --step=validate-rh
php artisan payroll:benchmark --employees=10000 --step=lock
```

## Métriques produites

| Métrique | Source |
|---|---|
| Durée calculate | timer dans `PayrollBenchmark` |
| Temps / employé | durée / employee_count |
| Requêtes SQL | compteur `DB::listen` actif pendant calculate (barrière N+1, #1594) |
| Requêtes / employé | requêtes SQL / employee_count (ordre de grandeur attendu < 20) |
| Pic mémoire | `memory_get_peak_usage` (delta) |
| total_gross / total_net | run après calcul |
| Durée validate-rh / lock | timers par étape |

## Objectif

- **Cible : clôture 10 000 employés < 30 min** (1800 s).
- **Alerte régression** : dégradation > 20 % vs le run précédent consigné
  ci-dessous → le script `payroll-benchmark.sh` échoue (exit 1) et il faut
  ouvrir une issue de perf.

## Historique des runs

| Date | Employés | Step | Durée calculate | Temps/employé | Requêtes SQL | Requêtes/employé | Pic mémoire | Env | Note |
|---|---|---|---|---|---|---|---|---|---|---|
| 2026-08-09 | 1 000 | calculate | 9,15 s | 9,2 ms | 11 000 | 11,0 | 4,0 Mo | local (PG 14, PHP 8.4, 4 vCPU) | Métrique N+1 (#1594) : 11 req/employé < 20 — pas de signature N+1 |
| 2026-08-09 | 100 | all | 1,04 s | 10,4 ms | — | — | 2,0 Mo | local (PG 16, PHP 8.4, 4 vCPU) | Premier run — pipeline validé |
| 2026-08-09 | 1 000 | all | 10,02 s | 10,0 ms | — | — | 4,0 Mo | local (PG 16, PHP 8.4, 4 vCPU) | ≈ objectif conseillé < 10 s |
| 2026-08-09 | 10 000 | all | 90,15 s | 9,0 ms | — | — | 50,0 Mo | local (PG 16, PHP 8.4, 4 vCPU) | **Cible F-12 : 90 s < 30 min ✔** (seed 1,04 s) |

*Les runs antérieurs au compteur N+1 (#1594) n'ont pas de métrique requêtes SQL (—).*

*Généré par `dev-hub/tools/payroll-benchmark.sh` (#1604).*
