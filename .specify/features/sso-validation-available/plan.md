## Plan technique
1. `SSOService::getCompanySSO()` (ou le contrôleur) : exposer `validation_available => false` dans la réponse `status`.
2. Mettre à jour `openapi.yaml` (schéma `SSOStatus` + descriptions des callbacks 501).
3. Test Feature sur le champ (manager principal → champ présent ; non-manager → 403).
4. Mise à jour doc sécurité si nécessaire. CHANGELOG.
