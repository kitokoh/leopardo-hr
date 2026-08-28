# Budgets de performance et guards N+1

> **Issue :** [MAT-014 #5872](https://github.com/kitokoh/leopardo-hr/issues/5872)
> **Registre :** `dev-hub/tools/performance-budgets.json`
> **Garde CI :** `dev-hub/tools/check-performance-budgets.sh` (job Hygiene Guards)
> **Tests :** `dev-hub/tools/tests/check-performance-budgets.test.sh` (5 scénarios)

## Objectif

Définir des budgets p95/p99, des plafonds de requêtes, les index obligatoires,
la pagination et des contrôles N+1. Les **scans lents sont signalés** à chaque
PR (acceptance MAT-014) ; un registre incohérent bloque la CI.

## Règles transverses (`rules`)

| Règle | Valeur |
|---|---|
| Pagination | Tout endpoint de liste tenant pagine (`paginate`/`limit` explicite) |
| N+1 | Aucune requête Eloquent dans une boucle |
| p95 cible | ≤ 300 ms (endpoints critiques listés : 300–700 ms) |
| p99 cible | ≤ 800 ms |

## Ce que signale le garde (warning non bloquant)

1. `->get()` / `->all()` non paginé dans un contrôleur API ;
2. requête Eloquent dans une boucle (`foreach`/`while`) — pattern N+1 ;
3. index obligatoires du registre absents des migrations (création à prévoir).

Le triage se fait dans l'issue du contexte concerné ; un signal répété sur un
endpoint critique devient un budget bloquant une fois mesuré en CI (k6,
`k6-load-smoke.yml`).

## Exécution locale

```bash
bash dev-hub/tools/check-performance-budgets.sh api
bash dev-hub/tools/tests/check-performance-budgets.test.sh
```

## Rollback

- Revert du commit ; registre + script autonomes sans état.
