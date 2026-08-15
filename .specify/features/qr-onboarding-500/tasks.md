## Tâches
- [ ] 1. Ouvrir `api/app/Modules/HR/Interfaces/Api/V1/Controllers/OnboardingQrController.php`.
- [ ] 2. Remplacer les 2 appels `findCompanyFromPublicSchema(...)` par `PlatformCompanyLookup::findOrFail(...)`.
- [ ] 3. Supprimer la méthode privée `findCompanyFromPublicSchema` + import `DB` devenu inutile.
- [ ] 4. Vérifier l'existence d'une suite de tests pour ce contrôleur ; en créer une si absente (Feature, tenant, 3 scénarios).
- [ ] 5. Mettre à jour CHANGELOG.md.
- [ ] 6. PR `fix/2266-...` avec `Closes #2266`, CI verte, merge.
