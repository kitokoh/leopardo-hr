# Mini-spec — Issue #3931

## Problème

`Header.vue:308` déclarait `const router = useRouter()` **dans** le handler
`handleSearch()` (`@keyup.enter`). `useRouter()` = `inject(routerKey)` :
invoqué hors du scope setup, l'inject échoue (aucune instance active) →
`router` est `undefined` → `router.getRoutes().filter(...)` lance un TypeError
à chaque Entrée dans la recherche du header.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Entrée dans la recherche header | Filtre les routes sans erreur console |
| Comportement (routes tenant exclues, fallback) | Inchangé |
| lint + build admin | 0 erreur |

## Correctif

`useRouter()` remonté au scope setup (avec les autres stores) ; le handler ne
fait plus que consommer `router`. Aucun changement de logique.

## Validation

`npm run lint` et `npm run build` verts sur front/admin-dashboard (pas de
framework de tests unitaires dans cette SPA — couverture e2e Playwright).

Closes #3931
