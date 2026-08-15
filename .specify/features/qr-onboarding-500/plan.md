## Plan technique
1. Remplacer le corps de `findCompanyFromPublicSchema()` par un appel à `PlatformCompanyLookup::findOrFail($companyId)` (garde non-pgsql déjà gérée par `table()`).
2. Supprimer la méthode privée dupliquée et ses usages (les 2 call-sites).
3. Ajouter un test Feature (`OnboardingQrControllerTest` ou suite existante) : 200 manager principal, 403 non-manager, pas de 500.
4. Vérifier que `scanEmployee`/`createEmployeeFromQr` utilisent aussi le lookup sûr.
5. CHANGELOG.
