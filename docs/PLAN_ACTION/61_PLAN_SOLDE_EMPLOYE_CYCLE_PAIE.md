# Plan 61 - Solde employe et cycle de paie

## Source

Point utilisateur 35 et 41.

## Objectif

Fournir un solde employe clair et un module paie simplifie mobile-first : montant du, avances, heures supplementaires, absences, retards, primes, paiements recus et reste.

## Statut

**Implemente et securise par tests.** Le backend expose le solde self-service `/api/v1/me/balance`, le solde manager `/api/v1/employees/{employee}/balance` et le resume mobile `/api/v1/payroll/mobile-summary`. Les apps employee/manager affichent maintenant un bloc solde avant les bulletins. Les tests `PayrollCycleIntegrationTest` couvrent le calcul, la deduction des avances et l'isolation tenant.

## Lots d'execution

### Lot 61.1 - Modele de cycle paie

- [x] Definir cadence entreprise : journalier, hebdomadaire, mensuel.
- [x] Ajouter settings : `pay_cycle`, `pay_day`, `week_start`, `currency`.
- [x] Verifier compatibilite multi-tenant.

### Lot 61.2 - Calcul solde employe

- [x] Service `PayrollCycleService`.
- [x] Entrants : bulletins valides/paye, avances declarees/recues, salaire de base fallback.
- [x] Sortie stable : `gross_due`, `advances`, `paid`, `remaining`, `period`.

### Lot 61.3 - Endpoints API

- [x] `GET /api/v1/me/balance`
- [x] `GET /api/v1/employees/{employee}/balance`
- [x] `GET /api/v1/payroll/mobile-summary`

### Lot 61.4 - Mobile employee

- [x] Ecran "Mon solde" : recu, reste, avances, historique.
- [x] UX claire, pas comptabilite complexe.

### Lot 61.5 - Mobile manager

- [x] Vue paie simplifiee : liste employes, montant du, avances, solde final.
- [x] Recalcul a la demande et cache si possible.

## Tests

- [x] Calculs feature.
- [x] Feature tests tenant/RBAC.
- [x] Tests API payload mobile.

## Criteres d'acceptation

- Un employe voit son solde personnel seulement.
- Un manager voit uniquement son perimetre.
- Les valeurs restent coherentes avec avances et pointage multi-sessions.
