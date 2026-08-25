<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5439 — Journal d'audit global : colonnes `module` et `request_id`.
 *
 * `audit_logs` (table tenant, schéma résolu par search_path) existe depuis
 * 2026-05-10 ; cette migration l'étend pour le journal d'audit global :
 * - `module`      — domaine métier (attendance, payroll, planning, hr, auth…)
 *   pour filtrer le journal par surface (exigence RGPD traçabilité #5439).
 * - `request_id`  — identifiant de corrélation (header X-Correlation-ID,
 *   helper `correlation_id()`, issue #1874) pour relier une action à une
 *   requête.
 *
 * Additive et idempotente (guards `schemaTableExists`/`schemaHasColumn`,
 * pattern #1962).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('audit_logs')) {
            return;
        }

        $schema = resolveTableSchema('audit_logs');

        Schema::table("{$schema}.audit_logs", function (Blueprint $table): void {
            if (! schemaHasColumn('audit_logs', 'module')) {
                $table->string('module', 100)->nullable()->index()->after('action');
            }
            if (! schemaHasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable()->index()->after('module');
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('audit_logs')) {
            return;
        }

        $schema = resolveTableSchema('audit_logs');

        Schema::table("{$schema}.audit_logs", function (Blueprint $table): void {
            if (schemaHasColumn('audit_logs', 'request_id')) {
                $table->dropIndex(['request_id']);
                $table->dropColumn('request_id');
            }
            if (schemaHasColumn('audit_logs', 'module')) {
                $table->dropIndex(['module']);
                $table->dropColumn('module');
            }
        });
    }
};
