# Plan 63 - Architecture heures de pointe et traitements differes

## Source

Point utilisateur 37 et 42.

## Objectif

Preparer le systeme aux pics 8h, 18h, fin semaine, fin mois et paie : Redis, queues, batch jobs, cache, notifications differees et traitement nocturne.

## Lots d'execution

### Lot 63.1 - Cartographie charge

- [x] Identifier endpoints sensibles : login, attendance check-in/out, today, tasks today, notifications, payroll summary.
- [x] Ajouter scenario k6 progressif si absent.
- [x] Mesurer latence et erreurs via workflow manuel `k6 Load Smoke - Leopardo RH` et scripts `dev-hub/load/k6/*`.

### Lot 63.2 - Cache lecture

- [x] Cache tenant-scoped court pour `dashboard/manager-digest`.
- [x] Cache tenant-scoped pour `schedules`.
- [x] Invalidation schedules sur create/update/delete.
- [x] Invalidation employees cache sur create/update/archive.

### Lot 63.3 - Queues et batch

- [x] Queues nommees documentees : `documents`, `pdf`, `payroll`, `notifications`, `webhooks`, `default`.
- [x] `queue:health-check` couvre Redis, profondeurs queues et `failed_jobs`.
- [x] Scheduler active `attendance:auto-close --threshold=12`.
- [x] Runbook worker mis a jour dans `DEPLOYMENT_GUIDE.md`.

### Lot 63.4 - Paiement en masse

- [x] Paiement en masse asynchrone via `POST /api/v1/payroll-runs/{payrollRun}/bulk-pay`.
- [x] Creation rapide avec statut pollable via Redis.
- [x] Jobs : paiement batch, PDF, documents de paiement et historique log.

## Tests

- [x] Queue jobs tests.
- [x] k6 smoke read-only.
- [x] Feature tests idempotence batch existants sur paiement en masse et documents.

## Criteres d'acceptation

- [x] Aucune requete mobile ne bloque sur traitement lourd.
- [x] Les traitements longs sont observables et retryable.
- [x] Les pics de pointage ne recalculent pas toute la paie.

## Etat 2026-06-01

Plan 63 livre cote socle backend/ops. Les prochaines optimisations doivent se faire sur mesures k6 reelles de staging : indexes DB par endpoint lent, augmentation horizontale worker, puis alerting externe si la profondeur queue depasse le seuil cible.
