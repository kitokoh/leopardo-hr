# Mini-spécification — Issue #3857

## Objectif

Rendre BulkPay fail-closed quand Redis (coordinateur anti-doublon) est indisponible. Avant : le dispatch continuait sans claim et le job traitait sans garde NX → deux requêtes concurrentes (retry, double-clic) pouvaient payer 2× les mêmes bulletins (mouvement d'argent).

## Correction

1. **`BulkPaymentController::bulkPay`** : `catch (Throwable)` → 503 `BULK_PAYMENT_COORDINATOR_UNAVAILABLE` + log `payroll.bulk_payment.redis_unavailable`. Aucun dispatch sans claim.
2. **`ProcessBulkPaymentJob::handle`** : la panne Redis pendant le claim NX d'un slip → `$redisUnavailable = true` + `break` (fini le `$claimed = true` de secours). Après la boucle : libération du claim du run + `throw RuntimeException` (retry `$tries=3`). Aucune avance marquée `payment_declared` après le point d'abort, run jamais `paid`, aucun audit de batch écrit.
3. Redis UP : comportement inchangé (409 si en cours, NX par slip, run `paid` si tous les slips traités).

## Critères d'acceptation

1. Redis down → `POST /bulk-pay` renvoie 503, zéro job dispatché.
2. Redis down pendant le job → lot aborté (exception), zéro slip marqué payé, claim du run libéré.
3. Redis up → 409/202 inchangés, tests existants verts.
4. PHPStan strict vert.

## Trace Spec Kit

Issue : #3857
Branche : `fix/3857-bulkpay-redis-fail-closed`
Date : 2026-08-15
