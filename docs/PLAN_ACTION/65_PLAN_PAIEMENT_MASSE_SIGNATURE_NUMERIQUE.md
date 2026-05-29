# Plan 65 - Paiement en masse et signature numerique

## Source

Points utilisateur 42 et 44.

## Objectif

Permettre au manager de valider plusieurs paiements et preparer la validation employee : confirmation, signature numerique future, documents et audit.

## Lots d'execution

### Lot 65.1 - Payment batch model

- Modeles : `PaymentBatch`, `PaymentItem`, `PaymentConfirmation`.
- Statuts : draft, processing, paid, partially_confirmed, confirmed, failed.
- Liens : employee, payroll period, salary advances, documents.

### Lot 65.2 - API paiement en masse

- `POST /api/v1/payment-batches`
- `GET /api/v1/payment-batches`
- `GET /api/v1/payment-batches/{id}`
- `POST /api/v1/payment-batches/{id}/mark-paid`
- Employee : `POST /api/v1/payment-confirmations/{id}/confirm`

### Lot 65.3 - Jobs asynchrones

- Apres mark-paid : notifications employees, generation documents, audit events.
- Reponse mobile immediate : "traitement en cours".

### Lot 65.4 - Signature future-ready

- Stocker acceptation employee : timestamp, device, IP si disponible, version document.
- Ne pas implementer signature cryptographique complete sans decision juridique.
- Preparer interface `SignatureProvider`.

## Tests

- RBAC manager/employee.
- Idempotence confirmation.
- Jobs dispatch.
- Documents async.

## Criteres d'acceptation

- Paiement en masse ne bloque pas l'application.
- Chaque employe peut confirmer sa reception.
- Audit financier complet et exploitable.
