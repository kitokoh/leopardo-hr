# ISSUE_4183 — CTA « Commencer gratuitement » → essai guidé

> Spec Kit : `.specify/features/4183-hero-cta-free-signup/spec.md` · Issue : #4183
> Branche : `fix/4183-hero-cta-free-signup`

## Correctif

- `front/web/src/app/(landing)/pricing/page.tsx` : liens héro + bas de page
  `/checkout?plan=free` → `/signup?source=pricing_free`.
- Hors périmètre (évite le conflit avec #4184) : `getPlanHref` (price '0'),
  fallback `planNameToCheckoutKey`, affichage `/checkout?plan=free` (#4195).

## Pourquoi /signup

L'essai guidé (`/signup`) est le funnel sans carte de la vitrine ; le checkout
n'a pas de config Free facturable et `free` y est un alias vers Pilot (#2907).
