# Spec — TenantMigrationService (Shared → Schema)

## 1. Contexte et objectif

Lorsqu'un client en mode **shared tenancy** (plans Trial, Starter ou Business) passe au plan **Enterprise**, ses données doivent être migrées depuis les tables partagées du schéma public vers un **schéma dédié** PostgreSQL isolé. Cette migration garantit une isolation complète des données, une meilleure performance et la conformité aux exigences de sécurité du plan Enterprise.

Le `TenantMigrationService` est responsable de l'orchestration complète de cette transition, incluant la sauvegarde, la copie des données, la vérification d'intégrité et la mise à jour des métadonnées de l'entreprise.

## 2. Endpoint API

```
POST /admin/companies/{id}/migrate-to-dedicated
Authorization: Bearer <super_admin_token>
Content-Type: application/json
```

**Accès :** SuperAdmin uniquement (protégé par `SuperAdminMiddleware`).

**Réponse succès (200) :**
```json
{
  "message": "Migration vers le schéma dédié terminée avec succès.",
  "company": { "id": "...", "schema_name": "company_7c9e6679", "tenancy_type": "dedicated" }
}
```

## 3. Étapes de migration (avec rollback)

| # | Étape | Description | Rollback |
|---|-------|-------------|----------|
| 1 | **Sauvegarde pré-migration** | `pg_dump` filtré par `company_id` de toutes les tables partagées (employees, attendance_logs, absences, payrolls, etc.) vers un fichier `.sql.gz` stocké dans `storage/backups/` | Restauration depuis le dump |
| 2 | **Création du schéma dédié** | Appel de la fonction SQL `create_tenant_schema(company.schema_name)` qui crée le schéma et toutes les tables dans le nouveau schéma isolé | `DROP SCHEMA ... CASCADE` |
| 3 | **Migration des données** | Copie séquentielle de toutes les données depuis les tables partagées vers les nouvelles tables du schéma dédié. Tables concernées : `departments`, `positions`, `schedules`, `sites`, `employees`, `employee_devices`, `devices`, `attendance_logs`, `absence_types`, `absences`, `leave_balance_logs`, `salary_advances`, `projects`, `tasks`, `task_comments`, `evaluations`, `payrolls`, `payroll_export_batches`, `payroll_export_items`, `company_settings` | Suppression du schéma + restauration du dump |
| 4 | **Mise à jour `tenancy_type`** | `UPDATE companies SET tenancy_type = 'dedicated' WHERE id = ?` | `UPDATE ... SET tenancy_type = 'shared'` |
| 5 | **Confirmation `schema_name`** | Vérification et mise à jour de `company.schema_name` si nécessaire | Annulation de la mise à jour |
| 6 | **Vérification d'intégrité** | Comparaison des comptages de lignes (`COUNT(*)`) entre chaque table source (filtrée par `company_id`) et chaque table destination dans le nouveau schéma. En cas d'écart, la migration est annulée | Rollback complet automatique |
| 7 | **Mise à jour du cache Redis** | Invalidation et régénération des clés Redis liées à l'entreprise : `tenant:{company_id}:config`, `tenant:{company_id}:schema` | Reconstruction du cache shared |
| 8 | **Notification Super Admin** | Envoi d'une notification (email + in-app) au Super Admin avec le résumé de la migration (nombre de lignes migrées, durée, statut) | Notification d'échec avec détails |

## 4. Gestion des erreurs

L'ensemble du processus s'exécute dans une **transaction PostgreSQL** (sauf l'étape 1 de sauvegarde qui est externe). En cas d'erreur à n'importe quelle étape (2 à 7), un **ROLLBACK** automatique est déclenché et le Super Admin est notifié immédiatement avec les détails de l'erreur capturés dans les `audit_logs`.

```php
try {
    DB::transaction(function () use ($company) {
        $this->createDedicatedSchema($company);
        $this->migrateAllData($company);
        $this->updateCompanyTenancy($company);
        $this->verifyIntegrity($company);
        $this->refreshRedisCache($company);
    });
    $this->notifySuperAdmin($company, 'success');
} catch (\Throwable $e) {
    $this->rollbackMigration($company);
    $this->notifySuperAdmin($company, 'failure', $e->getMessage());
    throw $e;
}
```

## 5. Estimation de performance

- **Temps d'indisponibilité estimé : < 30 secondes** pour une entreprise de 500 employés avec 2 ans d'historique.
- La sauvegarde (étape 1) est effectuée **avant** le début de la transaction pour minimiser le temps d'indisponibilité.
- Les données sont copiées avec `INSERT INTO ... SELECT * FROM ... WHERE company_id = ?` pour une performance optimale.

## 6. Exemple de squelette PHP

```php
<?php

namespace App\Services\Tenant;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Notifications\TenantMigrationCompleted;
use App\Notifications\TenantMigrationFailed;

class TenantMigrationService
{
    private array $migratableTables = [
        'departments', 'positions', 'schedules', 'sites',
        'employees', 'employee_devices', 'devices',
        'attendance_logs', 'absence_types', 'absences',
        'leave_balance_logs', 'salary_advances',
        'projects', 'tasks', 'task_comments',
        'evaluations', 'payrolls', 'payroll_export_batches',
        'payroll_export_items', 'company_settings',
    ];

    public function migrateToDedicated(Company $company): void
    {
        // Étape 1 : sauvegarde pré-migration
        $backupPath = $this->preMigrationBackup($company);

        try {
            DB::transaction(function () use ($company) {
                // Étape 2 : création du schéma dédié
                DB::statement("SELECT create_tenant_schema(?)", [$company->schema_name]);

                // Étape 3 : migration des données
                foreach ($this->migratableTables as $table) {
                    $this->migrateTable($company, $table);
                }

                // Étape 4-5 : mise à jour des métadonnées
                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['tenancy_type' => 'dedicated']);

                // Étape 6 : vérification d'intégrité
                $this->verifyIntegrity($company);

                // Étape 7 : mise à jour Redis
                Redis::del("tenant:{$company->id}:config");
                Redis::del("tenant:{$company->id}:schema");
            });

            // Étape 8 : notification succès
            $this->notifySuperAdmin($company, true, $backupPath);
        } catch (\Throwable $e) {
            $this->notifySuperAdmin($company, false, $backupPath, $e->getMessage());
            throw $e;
        }
    }

    private function migrateTable(Company $company, string $table): int
    {
        $schema = $company->schema_name;
        return DB::statement(
            "INSERT INTO {$schema}.{$table} SELECT * FROM public.{$table} WHERE company_id = ?",
            [$company->id]
        );
    }

    private function verifyIntegrity(Company $company): void
    {
        foreach ($this->migratableTables as $table) {
            $sharedCount = DB::table($table)
                ->where('company_id', $company->id)
                ->count();
            $dedicatedCount = DB::table("{$company->schema_name}.{$table}")->count();

            if ($sharedCount !== $dedicatedCount) {
                throw new \RuntimeException(
                    "Integrity check failed for {$table}: shared={$sharedCount}, dedicated={$dedicatedCount}"
                );
            }
        }
    }

    private function preMigrationBackup(Company $company): string
    {
        $path = storage_path("backups/migration_{$company->id}_" . now()->format('Ymd_His') . ".sql.gz");
        // pg_dump filtré par company_id
        return $path;
    }
}
```
