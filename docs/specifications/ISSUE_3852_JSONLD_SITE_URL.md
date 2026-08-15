# ISSUE_3852 — JsonLd.tsx : fallback SITE_URL sur le domaine interdit

**Statut**: Fixed (PR `fix/3852-jsonld-site-url`) · **Priorité**: P3 · **Module**: web/seo

## Constat

`front/web/src/components/JsonLd.tsx` utilisait
`https://gestionemployer-backend.vercel.app` comme fallback — le domaine
explicitement interdit par #1775 (entreprise US sans rapport) — alors que
`getSiteUrl()` (#2656) est la source canonique.

## Correctif

Fallback remplacé par `getSiteUrl()` (NEXT_PUBLIC_SITE_URL → DEFAULT_SITE_URL
marque → localhost dev). Vérifié : `tsc --noEmit` OK, plus aucune référence au
domaine interdit hors commentaires documentaires.
