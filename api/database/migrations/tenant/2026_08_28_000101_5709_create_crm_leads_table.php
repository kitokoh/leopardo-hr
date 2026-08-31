<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM client V0 : leads (tenant-scoped).
 *
 * Lead commercial D'UN TENANT (espace client). Distinct de
 * `marketing_leads` (leads d'acquisition de la plateforme, schéma public)
 * et du pipeline CRM commercial Platform — aucun remplacement.
 *
 * Schéma aligné sur le modèle main `CrmLead` et sur les schémas inline des
 * tests main (CrmImportFlowTest / CrmLeadConversionTest / CrmPilotSeederTest) :
 * `source`/`status` sont des whitelists contrôlées au niveau application
 * (Domain\Enums — jamais de valeur libre), `account_id`/`owner_id` sont des
 * uuid (cohérence avec les migrations #5708/#5717), `tags` est un json
 * nullable, `converted_at` horodate la conversion (#5717), soft deletes.
 *
 * Migration idempotente (garde #1962/#5431, pattern schemaTableExists), réf.
 * issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->uuid('account_id')->nullable();
                $table->uuid('owner_id')->nullable();
                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('title', 255)->nullable();
                $table->string('source', 20)->default('manual');
                $table->string('status', 20)->default('new');
                $table->unsignedSmallInteger('score')->default(0);
                $table->json('tags')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status'], 'crm_leads_company_status_idx');
                $table->index(['company_id', 'created_at'], 'crm_leads_company_created_idx');
                $table->index(['owner_id'], 'crm_leads_owner_idx');
            });

            DB::statement("COMMENT ON TABLE crm_leads IS 'Prospects (leads) CRM client : source/status whitelistés en application (Enums), score, tags json, conversion horodatée (#5709).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
