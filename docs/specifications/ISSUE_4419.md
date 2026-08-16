# ISSUE_4419 — Billing dashboard : boutons « Payer en ligne » en 422 (codes legacy)

> Spec Kit : `.specify/features/4419-billing-legacy-plan-codes/spec.md` · Issue : #4419
> Branche : `fix/4419-billing-legacy-plan-codes`

## Constat
`billing/page.tsx` envoie `starter`/`business` à `POST /billing/checkout`
(validation `Rule::in(PlanCode::values())` = free|pilot|operations|enterprise) → 422.

## Correctif
Codes canoniques `pilot|operations|enterprise` + `PLAN_LABELS` ×4 codes ; alias
legacy documenté (#4209).

## Vérification
- `rg "starter|business"` sur la page → 0 (hors commentaire).
- tsc/eslint/jest verts.
