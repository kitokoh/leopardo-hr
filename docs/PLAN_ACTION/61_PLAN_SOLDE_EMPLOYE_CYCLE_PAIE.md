# Plan 61 - Solde employe et cycle de paie

## Source

Point utilisateur 35 et 41.

## Objectif

Fournir un solde employe clair et un module paie simplifie mobile-first : montant du, avances, heures supplementaires, absences, retards, primes, paiements recus et reste.

## Lots d'execution

### Lot 61.1 - Modele de cycle paie

- Definir cadence entreprise : journalier, hebdomadaire, mensuel.
- Ajouter settings : `pay_cycle`, `pay_day`, `week_start`, `currency`.
- Verifier compatibilite multi-tenant.

### Lot 61.2 - Calcul solde employe

- Service `EmployeeBalanceService`.
- Entrants : attendance sessions, tasks performance, absences, salary advances, premiums, penalties, payroll runs.
- Sortie stable : `gross_due`, `advances`, `paid`, `remaining`, `period`.

### Lot 61.3 - Endpoints API

- `GET /api/v1/me/balance`
- `GET /api/v1/employees/{employee}/balance`
- `GET /api/v1/payroll/mobile-summary`

### Lot 61.4 - Mobile employee

- Ecran "Mon solde" : recu, reste, avances, historique.
- UX claire, pas comptabilite complexe.

### Lot 61.5 - Mobile manager

- Vue paie simplifiee : liste employes, montant du, avances, solde final.
- Recalcul a la demande et cache si possible.

## Tests

- Calculs unitaires.
- Feature tests tenant/RBAC.
- Tests API payload mobile.

## Criteres d'acceptation

- Un employe voit son solde personnel seulement.
- Un manager voit uniquement son perimetre.
- Les valeurs restent coherentes avec avances et pointage multi-sessions.
