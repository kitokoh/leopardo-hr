<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5714 — Sessions d'import CSV CRM (tenant-scoped).
 *
 * Une session matérialise le cycle preview → commit/cancel de l'import CSV :
 * le preview ne touche JAMAIS les tables cibles (accounts/contacts/leads),
 * le commit est un acte explicite et idempotent (claim atomique de statut),
 * l'annulation est possible avant commit.
 *
 * `company_id` non nullable + index (company_id, status) : isolation tenant
 * et requêtes de listing par statut. `raw_rows` stocke les lignes validées
 * pour le commit différé ; `preview_data` est masqué (PII) côté API (#5713).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_imports')) {
            Schema::create('crm_imports', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->uuid('company_id')->index();

                $table->string('entity_type', 20);
                $table->string('filename', 255);

                // previewed → committing → committed | failed | cancelled
                $table->string('status', 20)->default('previewed');

                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('valid_rows')->default(0);
                $table->unsignedInteger('error_rows')->default(0);

                $table->jsonb('columns')->nullable();
                $table->jsonb('preview_data')->nullable();
                $table->jsonb('errors')->nullable();
                $table->jsonb('raw_rows')->nullable();
                $table->jsonb('result')->nullable();

                $table->unsignedInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
                $table->unsignedInteger('committed_by')->nullable();
                $table->foreign('committed_by')->references('id')->on('employees')->nullOnDelete();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->foreign('cancelled_by')->references('id')->on('employees')->nullOnDelete();

                $table->timestampTz('committed_at')->nullable();
                $table->timestampTz('cancelled_at')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'entity_type']);
            });

            DB::statement("COMMENT ON TABLE crm_imports IS 'Sessions d''import CSV CRM : preview sans écriture, commit explicite idempotent, annulation, audit.'");
            DB::statement("COMMENT ON COLUMN crm_imports.status IS 'previewed|committing|committed|failed|cancelled — transition atomique (claim) anti double-commit.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_imports');
    }
};
