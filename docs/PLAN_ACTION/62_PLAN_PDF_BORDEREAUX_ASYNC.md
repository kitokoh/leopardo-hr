# Plan 62 - Generation PDF et bordereaux asynchrones

## Source

Point utilisateur 36 et 43.

## Objectif

Generer recus, bordereaux, justificatifs et fiches paiement sans bloquer l'application, via jobs, queues et stockage documentaire.

## Lots d'execution

### Lot 62.1 - Contrat document paiement

- Definir types : `payment_receipt`, `payment_slip`, `advance_receipt`, `payroll_summary`.
- Lier document a employee, company, payment batch ou salary advance.

### Lot 62.2 - Job PDF async

- Creer job `GeneratePaymentDocumentJob`.
- Queue dediee `documents` ou `payroll`.
- Retry, timeout, tags Horizon.
- Ne jamais generer PDF dans la requete mobile.

### Lot 62.3 - Endpoints API

- `GET /api/v1/me/payment-documents`
- `GET /api/v1/me/payment-documents/{id}/download`
- Manager : `GET /api/v1/payments/{id}/documents`

### Lot 62.4 - Mobile

- Employee : liste documents et telechargement/partage.
- Manager : statut generation documents apres paiement.

## Tests

- Queue assertion.
- Storage fake.
- Access control employee/manager.

## Criteres d'acceptation

- Paiement declare retourne vite.
- Document genere en arriere-plan.
- L'utilisateur voit un statut clair : en cours, disponible, erreur.
