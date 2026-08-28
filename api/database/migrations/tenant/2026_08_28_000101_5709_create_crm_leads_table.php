<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM client V0 : leads.
 *
 * Lead commercial D'UN TENANT (espace client). Distinct de
 * `marketing_leads` (leads d'acquisition de la plateforme, schéma public)
 * et du pipeline CRM commercial Platform — aucun remplacement.
 *
 * Invariants portés par le schéma :
 *   - `company_id` (UUID) obligatoire — isolation tenant stricte ;
 *   - CHECK sur `status` (new|contacted|qualified|converted|rejected) et
 *     `priority` (low|medium|high) : valeurs inconnues rejetées en base ;
 *   - `owner_id` indexé (membre du tenant ; la validité du propriétaire est
 *     contrôlée au niveau application/Policies, pattern employees existant) ;
 *   - indexes pipeline stage/owner/date pour les recherches paginées V1.
 *
 * Migration idempotente (garde #1962/#5431), réf. issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('company_name', 150)->nullable();
                $table->string('source', 40)->nullable();
                $table->string('status', 20)->default('new');
                $table->string('priority', 10)->default('medium');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('converted_opportunity_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_leads_company_status_idx');
                $table->index(['company_id', 'created_at'], 'crm_leads_company_created_idx');
                $table->index(['owner_id'], 'crm_leads_owner_idx');
                $table->index(['email'], 'crm_leads_email_idx');
            });

            // Statuts et priorités bornés : toute valeur inconnue est refusée.
            // (Blueprint::check indisponible dans cette version → SQL brut, pattern #5234.)
            DB::statement(
                "ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_status_check CHECK (status IN ('new', 'contacted', 'qualified', 'converted', 'rejected'))"
            );
            DB::statement(
                "ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_priority_check CHECK (priority IN ('low', 'medium', 'high'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
