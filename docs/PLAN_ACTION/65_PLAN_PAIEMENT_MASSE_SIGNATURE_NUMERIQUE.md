# Plan 65 - Paiement en masse et signature numerique

## Source

Points utilisateur 42 et 44.

## Objectif

Permettre au manager de valider plusieurs paiements et preparer la validation employee : confirmation, signature numerique future, documents et audit.

## Lots d'execution

### Lot 65.1 - Payment batch model

- [x] Modeles : `PaymentBatch`, `PaymentItem`, `PaymentConfirmation`.
- [x] Statuts : draft, processing, paid, partially_confirmed, confirmed, failed.
- [x] Liens : employee, payroll period, salary advances, documents/pay slips.

### Lot 65.2 - API paiement en masse

- [x] `POST /api/v1/payment-batches`
- [x] `GET /api/v1/payment-batches`
- [x] `GET /api/v1/payment-batches/{id}`
- [x] `POST /api/v1/payment-batches/{id}/mark-paid`
- [x] Employee : `POST /api/v1/payment-confirmations/{id}/confirm`

### Lot 65.3 - Jobs asynchrones

- [x] Apres mark-paid : generation documents async via `GeneratePaymentDocumentJob`.
- [x] Reponse mobile immediate : "traitement en cours".
- [ ] Notification employee dediee a brancher sur `CommunicationService` dans le prochain lot communication finance.

### Lot 65.4 - Signature future-ready

- [x] Stocker acceptation employee : timestamp, device, IP si disponible, version document.
- [x] Ne pas implementer signature cryptographique complete sans decision juridique.
- [ ] Preparer interface `SignatureProvider` quand le cadre juridique de signature sera tranche.

## Tests

- [x] RBAC manager/employee.
- [x] Idempotence confirmation.
- [x] Jobs dispatch.
- [x] Documents async.

## Criteres d'acceptation

- Paiement en masse ne bloque pas l'application.
- Chaque employe peut confirmer sa reception.
- Audit financier complet et exploitable.
