<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM client V0 : opportunités.
 *
 * Opportunité commerciale d'UN TENANT, rattachée à un pipeline et à un
 * stage du MÊME tenant. Le pipeline reste la source de vérité du cycle
 * (won/lost dérivés de `crm_pipeline_stages.is_won/is_lost`) ; `won_at` /
 * `lost_at` horodatent la transition pour l'analytique V1.
 *
 * Invariants portés par le schéma :
 *   - `company_id` (UUID) obligatoire — isolation tenant stricte ;
 *   - FK composites (pipeline_id, company_id) et (stage_id, company_id) :
 *     impossible de référencer un pipeline/stage d'un AUTRE tenant ;
 *   - CHECK amount >= 0 : aucun montant négatif ;
 *   - indexes pipeline/stage/owner/date pour les listes et dashboards.
 *
 * `account_id` (comptes, issue #5708) et `converted_from_lead_id` sont des
 * colonnes indexées SANS FK : les tables correspondantes arrivent dans
 * d'autres PR V0 — la cohérence est validée au niveau application.
 *
 * Migration idempotente (garde #1962/#5431), réf. issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('pipeline_id');
                $table->unsignedBigInteger('stage_id');
                $table->string('name', 150);
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('converted_from_lead_id')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->date('expected_close_date')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('source', 40)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('won_at')->nullable();
                $table->timestamp('lost_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'pipeline_id'], 'crm_opportunities_company_pipeline_idx');
                $table->index(['pipeline_id', 'stage_id'], 'crm_opportunities_pipeline_stage_idx');
                $table->index(['owner_id'], 'crm_opportunities_owner_idx');
                $table->index(['expected_close_date'], 'crm_opportunities_close_date_idx');
                $table->index(['company_id', 'created_at'], 'crm_opportunities_company_created_idx');

                // Cross-tenant : pipeline et stage doivent appartenir au MÊME tenant.
                $table->foreign(['pipeline_id', 'company_id'], 'crm_opportunities_pipeline_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('crm_pipelines')
                    ->cascadeOnDelete();
                $table->foreign(['stage_id', 'company_id'], 'crm_opportunities_stage_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('crm_pipeline_stages')
                    ->cascadeOnDelete();
            });

            // Montant jamais négatif. (Blueprint::check indisponible → SQL brut.)
            DB::statement(
                'ALTER TABLE crm_opportunities ADD CONSTRAINT crm_opportunities_amount_check CHECK (amount IS NULL OR amount >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
