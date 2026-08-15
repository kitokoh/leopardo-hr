## Plan technique
1. Dans la migration, AVANT `dropUnique`/`unique` : exécuter une suppression des doublons tenant-scopée :
   `DELETE FROM notification_preferences a USING notification_preferences b WHERE a.company_id = b.company_id AND a.employee_id = b.employee_id AND a.id < b.id` (garder le max id ; si colonne updated_at fiable, préférer updated_at puis id).
   Envelopper dans le search_path tenant courant (la migration tourne déjà dans le contexte tenant).
2. Garder les gardes `Schema::hasTable`/`hasColumn` existants.
3. Tester : petite suite ou script SQL de validation ; si un test de migration existe déjà dans la suite, l'étendre.
4. CHANGELOG.
