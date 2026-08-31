<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM client V0 : pipelines + stages (tenant-scoped).
 *
 * Le CRM commercial Leopardo (Platform/Marketing) n'est PAS touché : ces
 * tables appartiennent aux espaces client (tenants) et à l'API tenant.
 *
 * `crm_pipelines` est alignée sur le modèle main `CrmPipeline` (name,
 * is_default, stages json) ; `crm_pipeline_stages` porte l'ordre total et
 * les étapes gagnante/perdante.
 *
 * Invariants portés par le schéma :
 *   - `company_id` (UUID) non nullable sur chaque ligne — isolation tenant ;
 *   - UNIQUE (company_id, name) sur les pipelines : pas de doublon de nom
 *     dans un même tenant ;
 *   - FK composite (pipeline_id, company_id) → crm_pipelines(id, company_id)
 *     pour les stages : impossible de rattacher un stage au pipeline d'un
 *     AUTRE tenant (relation cross-tenant rejetée au niveau base) ;
 *   - UNIQUE (pipeline_id, position) : ordre de stage total dans un pipeline ;
 *   - CHECK position >= 0 et CHECK (is_won, is_lost) non simultanés.
 *
 * Migration idempotente (garde #1962/#5431, pattern schemaTableExists), réf.
 * issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->json('stages');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'name'], 'crm_pipelines_company_name_unique');
                // Clé d'intégrité pour les FK composites (id, company_id) des
                // tables filles : rend toute référence cross-tenant impossible.
                $table->unique(['id', 'company_id'], 'crm_pipelines_id_company_unique');

                $table->index(['company_id', 'created_at'], 'crm_pipelines_company_created_idx');
            });

            DB::statement("COMMENT ON TABLE crm_pipelines IS 'Pipelines CRM client (tenant) : étapes denormalisées (stages json) + ordre normalisé dans crm_pipeline_stages (#5709).'");
        }

        if (! schemaTableExists('crm_pipeline_stages')) {
            Schema::create('crm_pipeline_stages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('pipeline_id');
                $table->string('name', 100);
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('color', 20)->nullable();
                $table->boolean('is_won')->default(false);
                $table->boolean('is_lost')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['pipeline_id', 'position'], 'crm_pipeline_stages_pipeline_position_unique');
                $table->unique(['id', 'company_id'], 'crm_pipeline_stages_id_company_unique');
                $table->index(['company_id', 'position'], 'crm_pipeline_stages_company_pos_idx');

                // Cross-tenant : un stage doit référencer un pipeline du MÊME tenant.
                $table->foreign(['pipeline_id', 'company_id'], 'crm_pipeline_stages_pipeline_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('crm_pipelines')
                    ->cascadeOnDelete();
            });

            // Ordre strictement positif et étape « gagnée »/« perdue » exclusive.
            // (Blueprint::check indisponible dans cette version → SQL brut, pattern #5234.)
            DB::statement(
                'ALTER TABLE crm_pipeline_stages ADD CONSTRAINT crm_pipeline_stages_position_check CHECK (position >= 0)'
            );
            DB::statement(
                'ALTER TABLE crm_pipeline_stages ADD CONSTRAINT crm_pipeline_stages_won_lost_exclusive_check CHECK ((is_won = false) OR (is_lost = false))'
            );

            DB::statement("COMMENT ON TABLE crm_pipeline_stages IS 'Étapes normalisées des pipelines CRM client : ordre total (pipeline_id, position), won/lost exclusifs (#5709).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
