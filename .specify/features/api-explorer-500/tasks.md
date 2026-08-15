## Tâches
- [ ] 1. Localiser le littéral `<?php` dans `api-explorer.blade.php` (vérifier ligne exacte).
- [ ] 2. Le remplacer par `<?= '<?php' ?>` (échappement blade).
- [ ] 3. Scanner les autres vues blade pour des littéraux `<?php`/`<?=` dangereux.
- [ ] 4. Ajouter l'assertion de non-régression dans `OpenApiDocsTest`.
- [ ] 5. Lancer `php -l` sur la vue compilée / test Feature `OpenApiDocsTest` (pas de PHP local → s'appuyer sur la CI GitHub Actions).
- [ ] 6. Mettre à jour `CHANGELOG.md` (section `[Unreleased]`).
- [ ] 7. PR `fix/2265-...` avec `Closes #2265`, attendre checks verts, merger.
