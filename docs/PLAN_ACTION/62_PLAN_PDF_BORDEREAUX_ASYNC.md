# Plan 62 - Generation PDF et bordereaux asynchrones

## Source

Point utilisateur 36 et 43.

## Objectif

Generer recus, bordereaux, justificatifs et fiches paiement sans bloquer l'application, via jobs, queues et stockage documentaire.

## Lots d'execution

### Lot 62.1 - Contrat document paiement

- [x] Definir types : `payment_receipt`, `payment_slip`, `advance_receipt`, `payroll_summary`.
- [x] Lier document a employee, company, payroll run, pay slip ou salary advance via `payment_documents`.

### Lot 62.2 - Job PDF async

- [x] Creer job `GeneratePaymentDocumentJob`.
- [x] Queue dediee `documents`, retry, timeout, tags Horizon.
- [x] Ne jamais generer PDF dans la requete mobile : `mark-paid` cree un document `pending` puis dispatch le job.

### Lot 62.3 - Endpoints API

- [x] `GET /api/v1/me/payment-documents`
- [x] `GET /api/v1/me/payment-documents/{id}/download`
- [x] Manager : `GET /api/v1/payroll-runs/{id}/payment-documents`
- [x] Alias mobile : `GET /api/v1/payments/{id}/documents`

### Lot 62.4 - Mobile

- [x] Employee : liste documents et telechargement dans l'app mobile.
- [x] Manager : statut generation documents apres paiement dans l'app mobile.

## Tests

- [x] Queue assertion sur declaration paiement avance.
- [x] Storage fake sur telechargement.
- [x] Access control employee/manager.

## Criteres d'acceptation

- [x] Paiement declare retourne vite et cree un document `pending`.
- [x] Document genere en arriere-plan via queue `documents`.
- [x] L'utilisateur voit un statut clair : `pending`, `generating`, `available`, `failed`.
- [x] Employee/manager voient les documents disponibles ou en generation depuis les ecrans paie mobile.

## Notes implementation 2026-06-01

- Les bulletins historiques restent servis par `PaySlipPdfGenerator`.
- `payment_documents` devient l'index documentaire mobile-first pour les recus, bordereaux et justificatifs.
- `ProcessBulkPaymentJob` cree aussi des documents `payment_slip` pour les bulletins traites en masse.
- Lot 62.4 livre le 2026-06-01 : employee affiche `Documents paiement` dans l'ecran paie et manager ouvre les documents d'un cycle via l'action dossier sur chaque ligne paie.
