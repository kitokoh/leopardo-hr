# Plan 63 - Architecture heures de pointe et traitements differes

## Source

Point utilisateur 37 et 42.

## Objectif

Preparer le systeme aux pics 8h, 18h, fin semaine, fin mois et paie : Redis, queues, batch jobs, cache, notifications differees et traitement nocturne.

## Lots d'execution

### Lot 63.1 - Cartographie charge

- Identifier endpoints sensibles : login, attendance check-in/out, today, tasks today, notifications, payroll summary.
- Ajouter scenario k6 progressif si absent.
- Mesurer latence et erreurs.

### Lot 63.2 - Cache lecture

- Cache tenant-scoped pour dashboard manager, employees status, schedules, company settings.
- TTL court pour donnees temps reel.
- Invalidation sur mutations importantes.

### Lot 63.3 - Queues et batch

- Deporter : notifications bulk, PDF, recalcul paie, payment batch, auto-close attendance.
- Verifier workers Render / Horizon / Supervisor.
- Documenter runbook.

### Lot 63.4 - Paiement en masse

- Endpoint `POST /api/v1/payments/batches`.
- Creation batch rapide.
- Jobs : calcul, notification, documents, historique.

## Tests

- Queue jobs tests.
- k6 smoke read-only.
- Feature tests idempotence batch.

## Criteres d'acceptation

- Aucune requete mobile ne bloque sur traitement lourd.
- Les traitements longs sont observables et retryables.
- Les pics de pointage ne recalculent pas toute la paie.
