<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7711 — Raccordement de DeviceToken au trait BelongsToCompany.
 *
 * `device_tokens` (table tenant, 2026-05-18) n'avait jamais reçu la colonne
 * `company_id` alors que le modèle la listait déjà dans son $fillable et que
 * PushNotificationService la posait conditionnellement (garde
 * Schema::hasColumn). Cette migration matérialise la colonne pour que le
 * scope tenant du trait s'applique :
 * - `company_id` uuid NULLABLE (compatibilité lignes existantes) ;
 * - backfill depuis employees.company_id (le token appartient à un employé,
 *   lui-même mono-société) ;
 * - index (company_id, is_active) pour les fan-out de jobs push.
 *
 * Additive et idempotente (guards `schemaTableExists`/`schemaHasColumn`,
 * pattern #1962).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('device_tokens')) {
            return;
        }

        $schema = resolveTableSchema('device_tokens');

        if (! schemaHasColumn('device_tokens', 'company_id')) {
            Schema::table("{$schema}.device_tokens", function (Blueprint $table): void {
                $table->uuid('company_id')->nullable()->after('employee_id');
                $table->index(['company_id', 'is_active']);
            });

            // Backfill : la société du token est celle de son employé.
            $employeesSchema = resolveTableSchema('employees');
            DB::statement(<<<SQL
                UPDATE {$schema}.device_tokens dt
                SET company_id = e.company_id::uuid
                FROM {$employeesSchema}.employees e
                WHERE dt.employee_id = e.id
                  AND dt.company_id IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('device_tokens')) {
            return;
        }

        $schema = resolveTableSchema('device_tokens');

        if (schemaHasColumn('device_tokens', 'company_id')) {
            Schema::table("{$schema}.device_tokens", function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'is_active']);
                $table->dropColumn('company_id');
            });
        }
    }
};
