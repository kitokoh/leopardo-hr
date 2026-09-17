<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('audit_logs');

        if (schemaTableExists('audit_logs')) {
            Schema::table("{$schema}.audit_logs", function (Blueprint $table) {
                if (! schemaHasColumn('audit_logs', 'user_id')) {
                    $table->unsignedInteger('user_id')->nullable()->index()->after('company_id');
                }
                if (! schemaHasColumn('audit_logs', 'auditable_type')) {
                    $table->string('auditable_type', 100)->nullable()->after('action');
                }
                if (! schemaHasColumn('audit_logs', 'auditable_id')) {
                    $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
                }
                if (! schemaHasColumn('audit_logs', 'old_values')) {
                    $table->jsonb('old_values')->nullable()->after('auditable_id');
                }
                if (! schemaHasColumn('audit_logs', 'new_values')) {
                    $table->jsonb('new_values')->nullable()->after('old_values');
                }
                if (! schemaHasColumn('audit_logs', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('new_values');
                }
                if (! schemaHasColumn('audit_logs', 'user_agent')) {
                    $table->string('user_agent', 500)->nullable()->after('ip_address');
                }
                if (! schemaHasColumn('audit_logs', 'metadata')) {
                    $table->jsonb('metadata')->nullable()->after('user_agent');
                }
            });

            if (schemaHasColumn('audit_logs', 'target_type')) {
                DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN target_type DROP NOT NULL");
            }
            if (schemaHasColumn('audit_logs', 'target_id')) {
                DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN target_id DROP NOT NULL");
            }

            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable comme dans la generation `payrolls` : un audit plateforme
            // peut ne porter aucune entreprise (meme definition que la base migree,
            // ou la colonne a ete creee nullable).
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('action', 100);

            // Colonnes heritees de la generation `payrolls` (qui gagnait en ordre
            // d'execution) : conservees pour qu'une installation fraiche ait
            // exactement le meme schema que la base migree.
            $table->unsignedInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->index(['target_type', 'target_id']);
            // Nullable : `AuditLog::record()` les renseigne a null sans sujet
            // (et la migration de reconciliation 2026_05_12 ne les recree pas
            // puisqu'elles existent — les laisser NOT NULL casserait toute
            // ecriture d'audit sur une installation fraiche : 23502).
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('audit_logs');

        Schema::dropIfExists('audit_logs');
    }
};
