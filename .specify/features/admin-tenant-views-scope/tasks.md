## Tâches
- [ ] 1. Lister les routes tenant du router admin (payroll, leaves, contracts, recruitment, training, fleet, chat, webhooks, exports, reports, predictions, audit + settings/payroll/*).
- [ ] 2. Ajouter `meta.requiresTenant` sur ces routes.
- [ ] 3. Mettre à jour la sidebar (composant de nav) pour exclure les entrées tenant.
- [ ] 4. Ajouter un guard de redirection propre pour accès direct URL (message explicite).
- [ ] 5. Vérifier lint/build et les e2e admin existants (adapter si un test dépend d'une route tenant).
- [ ] 6. CHANGELOG + PR `fix/2272-...` `Closes #2272`, CI verte, merge.
