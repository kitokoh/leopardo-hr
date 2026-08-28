<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM client V0 : opportunités (tenant-scoped).
 *
 * Opportunité commerciale d'UN TENANT. Schéma aligné sur le modèle main
 * `CrmOpportunity` et sur le schéma inline de CrmLeadConversionTest :
 * `pipeline_id`/`lead_id`/`owner_id` sont des uuid NULLABLES (les PK des
 * tables référencées sont des bigint — jamais de FK, leçon CRM : la
 * cohérence est validée au niveau application), l'étape est portée par
 * `stage` (whitelist `CrmOpportunityStage`, défaut prospecting), `status`
 * (défaut open), montant/currency/date de clôture attendue, notes, soft
 * deletes.
 *
 * Migration idempotente (garde #1962/#5431, pattern schemaTableExists), réf.
 * issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->uuid('pipeline_id')->nullable();
                $table->uuid('lead_id')->nullable();
                $table->uuid('owner_id')->nullable();
                $table->string('name', 255);
                $table->string('stage', 80)->default('prospecting');
                $table->decimal('amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->date('expected_close_date')->nullable();
                $table->string('status', 10)->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status'], 'crm_opportunities_company_status_idx');
                $table->index(['company_id', 'stage'], 'crm_opportunities_company_stage_idx');
                $table->index(['company_id', 'created_at'], 'crm_opportunities_company_created_idx');
                $table->index(['owner_id'], 'crm_opportunities_owner_idx');
            });

            DB::statement("COMMENT ON TABLE crm_opportunities IS 'Opportunités CRM client : étape whitelistée (CrmOpportunityStage), pipeline_id/lead_id uuid sans FK (PK bigint côté cible — cohérence application), montant/date de clôture (#5709).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
