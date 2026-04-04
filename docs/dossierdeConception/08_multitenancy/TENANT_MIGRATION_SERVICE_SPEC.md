# TenantMigrationService — Spec complète
# Opération : Migration shared → schema (upgrade Business → Enterprise)

## RÈGLE ABSOLUE
Cette migration est IRRÉVERSIBLE côté schéma.
Elle doit être exécutée UNIQUEMENT par le Super Admin,
UNIQUEMENT avec une sauvegarde pg_dump vérifiée au préalable.

## ÉTAPES (7 étapes transactionnelles)

1. PRÉPARATION
   - Vérifier que pg_dump de shared_tenants est récent (< 1h)
   - Vérifier que company.status = 'active'
   - Créer le nouveau schéma : CREATE SCHEMA company_{uuid}
   - Mettre company.status = 'migrating' (bloque les logins pendant la migration)

2. COPIE DES TABLES
   - Copier toutes les tables de shared_tenants WHERE company_id = {id}
   - vers company_{uuid} (sans la colonne company_id)
   - Ordre : employees → schedules → attendance_logs → absences → payrolls
     (respecter les FK)

3. VÉRIFICATION D'INTÉGRITÉ
   - Compter les lignes source vs destination pour chaque table
   - Si écart détecté → ROLLBACK complet + alerte Super Admin

4. BASCULE
   - Mettre à jour companies SET tenancy_type = 'schema' WHERE id = {id}
   - Mettre à jour user_lookups pour pointer vers le nouveau schéma
   - Mettre company.status = 'active'

5. NETTOYAGE (différé — 30 jours après migration)
   - Supprimer les données de cette entreprise dans shared_tenants
   - NE PAS supprimer immédiatement (filet de sécurité 30 jours)

6. NOTIFICATION
   - Email au gestionnaire principal : "Votre compte Enterprise est activé"
   - Log dans admin_audit_logs

7. ROLLBACK (si étape 3 échoue)
   - DROP SCHEMA company_{uuid} CASCADE
   - Remettre company.status = 'active' (reste en shared)
   - Alerte Super Admin avec détail de l'erreur

## TESTS OBLIGATOIRES
- MigrationIntegrityTest → compte les lignes avant/après
- MigrationRollbackTest → simule une erreur en étape 3, vérifie le rollback
- MigrationSchemaIsolationTest → après migration, vérifie que shared ne
  contient plus les données de cette entreprise (après les 30 jours)

## PROMPT D'EXÉCUTION
À ajouter dans docs/PROMPTS_EXECUTION/v2/backend/CC-03_A_CC-06_MODULES.md comme tâche CC-07 :
"Implémenter TenantMigrationService selon TENANT_MIGRATION_SERVICE_SPEC.md"
Semaine : 9 (après que le module Super Admin soit stable)
