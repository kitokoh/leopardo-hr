# ADR-0011 — Billing/Payroll Domain Boundary: Breaking the Circular Dependency

**Date:** 2026-08-01  
**Status:** Accepted  
**Issue:** #1395  

---

## Context

The architecture defines 19 DDD modules that must remain decoupled at the Domain layer.
Modules expose cross-boundary dependencies exclusively through `Domain/Contracts/` interfaces,
never via direct `use` imports of another module's `Domain/Models`.

A code audit (2026-07-29) found two circular imports at the Domain level between `Billing` and `Payroll`:

```
Billing\Domain\Models\Invoice  → uses → Payroll\Domain\Models\Payment
Payroll\Domain\Models\Payment  → uses → Billing\Domain\Models\Invoice

Billing\Domain\Models\Partner  → uses → Payroll\Domain\Models\Commission
Payroll\Domain\Models\Commission → uses → Billing\Domain\Models\Partner
```

These cycles prevent either module from being evolved, tested, or extracted independently.

## Decision

**The natural dependency direction is Payroll → Billing** (a payment settles an invoice;
a commission is earned per referral tracked by Billing). This direction is preserved and correct.

**The reverse direction (Billing → Payroll) is broken** using Eloquent's runtime FQCN string
resolution instead of a compile-time `use` import:

- `Invoice::payments()` returns `$this->hasMany(\App\Modules\Payroll\Domain\Models\Payment::class)`  
  — no `use App\Modules\Payroll\Domain\Models\Payment;` import in `Invoice.php`.

- `Partner::commissions()` returns `$this->hasMany(\App\Modules\Payroll\Domain\Models\Commission::class)`  
  — no `use App\Modules\Payroll\Domain\Models\Commission;` import in `Partner.php`.

The PHPDoc `@return` annotations use the fully-qualified class name so that IDEs and PHPStan
can still type-check the relation return type without requiring the static import.

## Alternatives Considered

1. **Move `Payment` and `Commission` into `Billing\Domain\Models\`** — semantically valid
   (`Payment` settles a `Billing\Invoice`; `Commission` is a `Billing\Partner` earning), but
   would require updating ~20 files across controllers, services, tests, and events that
   already reference `Modules\Payroll\Domain\Models\Payment`. Risk/reward not justified when
   the FQCN approach achieves the same compile-time isolation.

2. **Introduce a `Billing\Domain\Contracts\PaymentRepositoryInterface`** — the full DDD
   pattern for bi-directional relations. Deferred to a future refactor; the immediate
   goal is to eliminate the static import cycle, not to redesign the relation model.

## Consequences

- No circular compile-time import at the Domain level between `Billing` and `Payroll`.
- `Billing\Invoice` and `Billing\Partner` remain independently testable and extractable.
- The `payments()` and `commissions()` Eloquent relations continue to work identically
  at runtime — Eloquent resolves the class from the string on first call.
- PHPStan can still verify the `@return HasMany<Payment, $this>` annotation because the
  FQCN is provided in the docblock.
- Future work: a deptrac or similar module-boundary CI guard should be added (see #1395)
  to prevent reintroduction of cross-Domain imports.
