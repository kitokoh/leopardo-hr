# ISSUE_3854 — FAQ vitrine : anciens plans Free/Pilot/Operations

**Statut**: Fixed (PR `fix/3854-faq-pricing-plans`) · **Priorité**: P2 · **Module**: web

## Constat

Les textes FAQ citaient les plans Free/Pilot/Operations, superseded par le
catalogue Starter 29€ / Business 79€ / Enterprise 199€ (#3247) :
`faq-page.ts` (4 locales), FAQ `/pricing` (4 locales), fallback `seo.ts`.

## Correctif

- `faq-page.ts` + FAQ `/pricing` : alignés sur main par un agent concurrent
  (wording adopté de main, équivalent).
- Résiduel corrigé ici : `seo.ts:444` (fallback FR « Selectionnez… plan »)
  citait encore Free/Pilot/Operations → Starter/Business/Enterprise.
- Vérifié : `tsc --noEmit` OK ; plus aucune référence aux anciens noms dans
  `front/web/src` (hors commentaires historiques).
