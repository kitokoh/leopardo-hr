<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('notification_preferences');
        if ($schema === null || ! schemaTableExists('notification_preferences')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Issue #2268 : des doublons (company_id, employee_id) existent sur les
        // tenants dont la table a été créée sans la contrainte UNIQUE (avant
        // 2026_05_22_000002) → l'ADD CONSTRAINT échouerait en unique_violation
        // et le conteneur Render ne booterait pas. On dé-duplique d'abord : par
        // paire (company_id, employee_id), seule la ligne la plus récente est
        // conservée (`updated_at` maximal, puis `id` maximal en départage —
        // `IS NOT DISTINCT FROM` couvre les `updated_at` NULL). Idempotent :
        // sans doublon, le DELETE ne supprime rien (retry Render sûr).
        DB::statement(<<<SQL
DELETE FROM "{$schema}"."notification_preferences" a
USING "{$schema}"."notification_preferences" b
WHERE a.company_id = b.company_id
  AND a.employee_id = b.employee_id
  AND (a.updated_at < b.updated_at
       OR (a.updated_at IS NOT DISTINCT FROM b.updated_at AND a.id < b.id))
SQL);

        DB::statement(<<<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = '{$schema}'
          AND t.relname = 'notification_preferences'
          AND c.conname = 'notification_preferences_company_employee_unique'
    ) THEN
        ALTER TABLE "{$schema}"."notification_preferences"
            ADD CONSTRAINT notification_preferences_company_employee_unique
            UNIQUE (company_id, employee_id);
    END IF;
END \$\$;
SQL);
    }

    public function down(): void
    {
        $schema = resolveTableSchema('notification_preferences');
        if ($schema === null || ! schemaTableExists('notification_preferences') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"notification_preferences\" DROP CONSTRAINT IF EXISTS notification_preferences_company_employee_unique");
    }
};
