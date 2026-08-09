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
| Pic mémoire | `memory_get_peak_usage` (delta) |
| total_gross / total_net | run après calcul |
| Durée validate-rh / lock | timers par étape |

## Objectif

- **Cible : clôture 10 000 employés < 30 min** (1800 s).
- **Alerte régression** : dégradation > 20 % vs le run précédent consigné
  ci-dessous → ouvrir une issue de perf.

## Historique des runs

| Date | Employés | Step | Durée calculate | Temps/employé | Pic mémoire | Env | Note |
|---|---|---|---|---|---|---|---|
| (à remplir) | | | | | | | |

*Généré par `dev-hub/tools/payroll-benchmark.sh` (#1604).*
