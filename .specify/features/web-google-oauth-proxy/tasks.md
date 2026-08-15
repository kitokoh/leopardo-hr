## Tâches

> Implémenté le 2026-08-15 (PR #???) — session agent dev, branche `fix/2277-web-google-oauth-proxy`.
- [x] 1. Localiser les constructions d'URL Google OAuth dans login/page.tsx (L135-141, L484).
- [x] 2. Les remplacer par le chemin proxy same-origin `/api/v1/auth/google`.
- [x] 3. Vérifier le proxy `/api/v1/[...path]` couvre bien auth/google*.
- [x] 4. Vérifier l'absence d'autres URL directes Render dans les composants d'auth.
- [x] 5. Lint + build web. CHANGELOG.
- [x] 6. PR `fix/2277-...` `Closes #2277`, CI verte, merge.
