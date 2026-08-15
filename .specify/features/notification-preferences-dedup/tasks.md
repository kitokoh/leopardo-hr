## Tâches
- [ ] 1. Lire la migration existante `2026_08_03_000001_align_notification_preferences_unique_key.php`.
- [ ] 2. Insérer l'étape de déduplication SQL avant l'ajout de la contrainte UNIQUE (garder ligne la plus récente par paire company_id+employee_id).
- [ ] 3. Vérifier l'idempotence (retry Render) et les gardes existants.
- [ ] 4. Ajouter un test (Feature ou test de migration) qui crée des doublons puis exécute la migration et vérifie UNIQUE + dédup.
- [ ] 5. CHANGELOG + PR `fix/2268-...` `Closes #2268`, CI verte, merge.
