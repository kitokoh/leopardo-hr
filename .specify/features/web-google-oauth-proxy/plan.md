## Plan technique
1. Corriger le href du bouton Google : `/api/v1/auth/google` (proxy same-origin) — le proxy Next route déjà `/api/v1/[...path]` (vérifier).
2. Vérifier qu'aucun autre composant d'auth ne construit d'URL directe vers Render.
3. Vérifier le handler de callback (`/auth/google/callback` côté Next si présent).
4. Lint + build. CHANGELOG.
