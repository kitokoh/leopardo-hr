<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vague QA 2026-08-14 — `audit_logs.auditable_id` était BIGINT alors que le
 * morph audit est polymorphe : `employees` (int) mais aussi `companies`
 * (UUID, HasUuids). `AuditLog::create(['auditable_type' => Company::class,
 * 'auditable_id' => $uuid])` → `invalid input syntax for type bigint` → 500.
 *
 * Cas constaté : `PlatformCompanyController::updateCountry()` (réparation du
 * pays, #1873) — l'audit trail `tenant_country_changed` plantait
 * systématiquement sur main.
 *
 * Correctif : `auditable_id` passe en varchar(36) (accepte les deux formes :
 * UUID et identifiants numériques existants convertis en chaîne).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('audit_logs') || ! schemaHasColumn('audit_logs', 'auditable_id')) {
            return;
        }

        $columnType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'audit_logs' AND column_name = 'auditable_id'"
        )?->data_type;

        if ($columnType === 'character varying' || $columnType === 'uuid') {
            return;
        }

        if ($columnType === 'bigint') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE varchar(36) USING auditable_id::varchar');
        }
    }

    public function down(): void
    {
        // Pas de retour arrière automatique : les valeurs UUID stockées ne
        // peuvent pas repasser en bigint sans perte.
    }
};
