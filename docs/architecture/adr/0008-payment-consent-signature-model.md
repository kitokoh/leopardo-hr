# ADR 0008 - Payment consent/signature model (no premature PKI)

## Status

Accepted.

**Date**: 2026-07-25

## Context

PA2-PAY-006 asks for a "prepared digital signature" model for payment
confirmations: a consent/signature model with an audit trail, explicitly
without over-engineering a premature cryptographic (PKI/certificate) stack.

The related, more concrete ticket PA2-PAY-016 ("simple digital signature")
already shipped the actual mechanism: `PaymentConsentSignatureService`
(`api/app/Modules/Payroll/Infrastructure/Services/PaymentConsentSignatureService.php`)
plus the `payment_confirmations` table/`PaymentConfirmation` model, wired
into `PaymentBatchController::confirm()`. This document is the missing
"model/doc" artifact PA2-PAY-006 asked for: it describes the design that
already exists in code and closes the gap between the two tickets, rather
than introducing a second, competing signature mechanism.

## Decision

### What "signature" means here

There is no asymmetric key pair, no certificate authority, and no PKCS#7 /
CMS envelope. A "signature" is a **timestamped, tamper-evident consent
hash**: a SHA-256 digest computed over a canonical JSON payload that binds
together the exact facts of the confirmation:

```php
[
    'payment_item_id'   => $item->id,
    'payment_batch_id'  => $item->payment_batch_id,
    'employee_id'       => $item->employee_id,
    'company_id'        => $item->company_id,
    'amount'            => number_format((float) $item->amount, 2, '.', ''),
    'currency'          => $item->currency,
    'document_version'  => $documentVersion,
    'confirmed_at'       => $confirmedAt->toIso8601String(),
]
```

Keys are sorted (`ksort`) before hashing so the digest is stable regardless
of array construction order. `PaymentConsentSignatureService::verify()`
recomputes the hash from the same facts and compares it against the stored
value, so any tampering with amount/currency/employee/instant after the
fact is detectable without needing a certificate chain.

### What gets recorded (the "model")

Each employee confirmation ("I received this payment") is one
`payment_confirmations` row (`PaymentConfirmation` model):

| Field | Purpose |
|---|---|
| `document_hash` | The SHA-256 consent hash described above. |
| `document_version` | Lets the hashed payload shape evolve over time without breaking old confirmations' verifiability. |
| `device_signature` | Opaque client-supplied string (e.g. a mobile device/session identifier). Not a cryptographic signature — a weak binding to "which device this consent came from", useful for support/dispute triage. |
| `ip_address` / `user_agent` | Standard request provenance, same as any other sensitive mutation in the API. |
| `confirmed_at` | The instant the hash is bound to. |
| `metadata` | Free-form JSON for future extension (e.g. geolocation) without a migration. |

Confirmation is idempotent: a second `confirm()` call for the same
`payment_item_id` returns the existing row rather than creating a
duplicate or re-hashing with a new timestamp (see
`PaymentBatchController::confirm()`).

### Audit trail

`PaymentConfirmation` uses the existing platform-wide `Auditable` trait
(`App\Shared\Traits\Auditable`, the same mechanism already attached to
`SalaryAdvance` for PA2-PAY-001). Every `created` confirmation writes one
`audit_logs` row (`auditable_type = PaymentConfirmation`, `new_values`
including the resulting `document_hash`, `user_id` = confirming employee).
This is deliberately a *second*, independent trail from the hash itself:
the hash lets you verify a single confirmation's integrity in isolation;
`audit_logs` lets you reconstruct *when and by whom* the confirmation
happened alongside every other audited action in the tenant, using the
exact same query surface operators already use for payroll/HR audit
review.

### Why not a real PKI now

- No employee/manager currently holds a private key, and issuing one
  (enrollment, revocation, key storage) is a multi-month PKI programme in
  itself, disproportionate to the actual requirement: **provable consent
  binding**, not **legal-grade non-repudiation**.
- The hash-based model already satisfies the concrete need this ticket set
  out to solve: tamper detection on the confirmation facts, an audit trail,
  and a stable `document_version` so the contract can evolve.
- If a future requirement needs true non-repudiable, legally-binding
  e-signatures (e.g. a jurisdiction mandating qualified electronic
  signatures for payslip acknowledgement), that is a distinct, larger
  initiative — likely integrating a third-party e-signature provider —
  and should get its own ticket/ADR rather than being retrofitted here.

## Consequences

- PA2-PAY-006 and PA2-PAY-016 now point at the same implementation; no
  duplicate signature mechanism should be introduced for either ticket.
- `PaymentConfirmation` changes now also appear in `audit_logs`, consistent
  with every other sensitive Payroll model.
- Any future consumer that wants to verify a confirmation's integrity
  should use `PaymentConsentSignatureService::verify()` rather than
  re-implementing hash comparison inline.
