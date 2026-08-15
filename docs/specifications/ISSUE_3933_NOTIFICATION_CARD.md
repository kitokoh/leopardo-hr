# Mini-spec — Issue #3933

## Problème

`NotificationPanel.vue:19` utilisait `card-lg` — classe inexistante (ni utility
Tailwind ni token du design system, seule occurrence du repo) → le toast de
notification avait un ring mais **aucun fond** : texte gris sur gris, illisible.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Toast de notification | Fond lisible (token design system) |
| lint + build admin | 0 erreur |

## Correctif

`card-lg` remplacé par le token `.card` du design system (`bg-surface-bright/40`,
backdrop-blur, border, shadow-glass) ; `ring-black/5` (variante moderne de
`ring-opacity-5`).

## Validation

`npm run lint` + `npm run build` verts (admin-dashboard).

Closes #3933
