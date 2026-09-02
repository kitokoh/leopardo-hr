<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5812 (FUEL-018) — sessions d'import CSV FuelStation.
 *
 * Cycle preview → commit/cancel (pattern CRM #5714) : le preview ne touche
 * JAMAIS les tables cibles (équipements, produits, shifts, relevés), le
 * commit est un acte explicite IDEMPOTENT (claim atomique de statut), le
 * rollback logique est possible avant commit. Lignes validées ligne par
 * ligne ; limites de taille/lignes côté Request ; permissions et audit.
 *
 * `company_id` non nullable + index (company_id, status) : isolation tenant.
 * `raw_rows` stocke les lignes validées pour le commit différé ;
 * `preview_data` ne contient aucune PII (descriptions redacted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_imports')) {
            Schema::create('fuel_imports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                // products | pumps | tanks | shifts | readings
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
                $table->unsignedInteger('committed_by')->nullable();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->timestampTz('committed_at')->nullable();
                $table->timestampTz('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_imports_company_status_idx');
                $table->index(['company_id', 'created_at'], 'fuel_imports_company_created_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_imports IS 'Sessions d import CSV FuelStation (preview -> commit/cancel, idempotent, redacted) — FUEL-018 (#5812).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_imports');
    }
};
