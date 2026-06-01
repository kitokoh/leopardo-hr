# Plan 60 - Validation double des avances et paiements

## Source

Point utilisateur 34.

## Objectif

Securiser le workflow avance salaire : demande employe, decision manager, declaration paiement, confirmation reception employe.

## Workflow cible

1. Employe cree une demande d'avance.
2. Manager approuve ou refuse.
3. Manager marque l'avance comme envoyee/payee.
4. Employe confirme reception.
5. Historique complet auditable.

## Lots d'execution

### Lot 60.1 - Etat metier

- Etendre `salary_advances` avec statuts ou champs :
  - `approved_at`, `approved_by`
  - `paid_at`, `paid_by`
  - `received_at`, `received_by`
  - `payment_reference`, `payment_note`
- Ne pas casser les statuts existants.

### Lot 60.2 - Endpoints API

- Ajouter `PUT /api/v1/salary-advances/{id}/mark-paid`.
- Ajouter `PUT /api/v1/salary-advances/{id}/confirm-received`.
- RBAC : manager principal/RH pour mark-paid, proprietaire employe pour confirm-received.
- Validation : impossible de payer une avance non approuvee, impossible de confirmer si non payee.

### Lot 60.3 - Mobile manager

- Ajouter action "Marquer envoyee" sur avances approuvees.
- Demander reference/note optionnelle.
- Rafraichir liste et afficher statut complet.

### Lot 60.4 - Mobile employee

- Afficher "En attente reception" quand manager a marque paye.
- Ajouter bouton "J'ai recu".
- Ajouter notification.

## Tests

- Feature tests RBAC et transitions.
- Repository tests mobile routes.
- Notification/event audit.

## Criteres d'acceptation

- Aucun saut d'etape possible.
- Historique financier lisible par manager et employe.
- Les anciennes avances continuent d'etre listables.

## Statut - 2026-06-01

**Etat : implemente et securise par tests.**

- Routes livrees : `manager-approve`, `mark-paid`, `confirm-received`.
- Mobile manager : action de validation manager puis declaration paiement avec reference/note.
- Mobile employee : confirmation de reception quand le paiement est declare.
- OpenAPI et matrice frontend/API alignes.
- Tests ajoutes : `SalaryAdvanceSecurityTest` couvre le chemin complet et les interdictions de saut d'etape.
- Fixtures tests mis a jour : `CreatesMvpSchema.php` et `mvp_schema.pgsql.sql` portent les colonnes Plan 60.
