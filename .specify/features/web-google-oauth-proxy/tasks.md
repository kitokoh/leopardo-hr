## Tâches
- [ ] 1. Localiser les constructions d'URL Google OAuth dans login/page.tsx (L135-141, L484).
- [ ] 2. Les remplacer par le chemin proxy same-origin `/api/v1/auth/google`.
- [ ] 3. Vérifier le proxy `/api/v1/[...path]` couvre bien auth/google*.
- [ ] 4. Vérifier l'absence d'autres URL directes Render dans les composants d'auth.
- [ ] 5. Lint + build web. CHANGELOG.
- [ ] 6. PR `fix/2277-...` `Closes #2277`, CI verte, merge.
