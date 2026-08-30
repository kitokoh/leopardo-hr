# Pilot gates — TravelAgency (TRAVEL-1011, issue #6124)

> **Registre :** `dev-hub/tools/pilot-gates.json` (entrée `travel`, 9 gates, `go_decision: pending`).
> **Garde CI MAT-018 (#5876) :** aucun GO prématuré possible.

## 9 gates

| # | Gate | Preuve attendue |
|---|---|---|
| 1 | `manifest` | Manifest + activation tenant (TRAVEL-101..107) — flag + seed |
| 2 | `core_flow` | Parcours réseau → trajet → réservation → paiement → billet → check-in |
| 3 | `api_security` | Policies, RBAC travel.*, tests cross-tenant 401/403/404 |
| 4 | `runbook` | `RUNBOOK_PILOT_TRAVEL.md` + `RECETTE_UAT_TRAVEL.md` validés |
| 5 | `security_review` | `AUDIT_SECURITE_RGPD_TRAVEL.md` — 0 anomalie bloquante |
| 6 | `performance` | Budgets p95 & index (MAT-014) — endpoints réservations/rapports |
| 7 | `observability` | Outbox (dead-letter = 0), jobs planifiés, erreurs supervisées |
| 8 | `golden_journey` | GJ-TRAVEL-01 vert (`TravelGoldenJourneyTest`) |
| 9 | `recette` | Recette UAT signée par le principal (TRAVEL-1012) |

## Drill log

Chaque drill est consigné dans `docs/ops/RUNBOOK_DRILLS_LOG.md` :

| Date | Drill | Exécutant | Résultat | Preuve |
|---|---|---|---|---|
| (à remplir au pilote) | Kill switch flag `travelagency` | — | — | — |
| (à remplir au pilote) | Restore scratch tenant pilote | — | — | — |
| (à remplir au pilote) | Reprise outbox (replay) | — | — | — |
