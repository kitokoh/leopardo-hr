# RUNBOOK — Pilote TravelAgency (tenant pilote, kill switch, backup, rollback)

> **Issue :** [TRAVEL-1010 #6123](https://github.com/kitokoh/leopardo-hr/issues/6123) — runbook pilote + recette UAT
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) (`pilot-gates.json`, 9 gates — aucun GO prématuré)
> **Références :** `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md` (docs/GESTION_PROJET)

## 1. Cadre du pilote

Le pilote TravelAgency démarre **uniquement** quand les 9 gates de
`dev-hub/tools/pilot-gates.json` (entrée `travel`) sont `validated` :
manifest, core flow, API/Policies, runbook, sécurité/RGPD, performance,
observabilité, golden journey GJ-TRAVEL-01, recette signée.

## 2. Tenant pilote

| Élément | Valeur cible |
|---|---|
| Tenant | `travel-pilot-001` (déterministe) |
| Pays | CM (premier marché) — devise XAF |
| Rôles | `principal` (manager pilote), `rh` (supervision), `manager` (opérations) |
| Données | Synthétiques uniquement (`leopardo:travel:seed-demo`, idempotent) |
| Feature flag | `travelagency` tenant-scope, désactivation coupante (kill switch) |

## 3. Préparation (ordre)

```bash
# 1. Activer la verticale + seed géo + données démo (idempotent)
php artisan leopardo:travel:seed-demo travel-pilot-001

# 2. Vérifier la préparation (rapport daté)
php artisan leopardo:travel:pilot-check --tenant=travel-pilot-001

# 3. Référentiel métier (via API, rôle principal) : compagnies, routes,
#    trajets publiés, tarifs — puis une réservation de bout en bout.
```

## 4. Kill switch et désactivation

- Feature flag `travelagency` : désactivation coupante → tout
  `GET/POST /api/v1/travel/*` répond 403 (middleware `module.travelagency`).
- En cas d'incident : désactiver le flag AVANT toute investigation
  (fail-closed), puis restaurer après résolution.
- La boutique publique est également coupée (même flag + jeton).

## 5. Backup / restauration / rollback

- Backup : sauvegarde PostgreSQL du schéma tenant (`pg_dump`) + secrets
  (jeton boutique, secret callbacks) hors VCS.
- Restauration : procédure standard `RUNBOOK_BACKUP_RESTORE.md`.
- Rollback : le flag coupé + l'absence d'écritures Accounting (contrat
  d'événements, TRAVEL-417) garantissent une sortie propre sans données
  résiduelles exploitables.

## 6. Supervision

- Outbox : `travel:outbox-dispatch` (1 min), dead-letters à surveiller.
- Jobs : `travel:expire-bookings` (5 min), `leopardo:travel:expire-adverts`,
  `leopardo:travel:settle-sales` (quotidien).
- KPIs pilote : réservations/jour, taux de confirmation, écart de caisse,
  remboursements, dead-letter outbox = 0.

## 7. Drill log

Chaque drill (restauration, kill switch, reprise outbox) est consigné dans
`docs/ops/RUNBOOK_DRILLS_LOG.md` avec date, exécutant, résultat et preuve.
