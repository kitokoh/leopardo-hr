<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM V0 : table tenant-scoped des opportunités.
 *
 * Une opportunité est une affaire en cours rattachée à un pipeline et
 * (optionnellement) à un lead. `amount` est le montant attendu, `stage`
 * l'étape courante du pipeline, `status` l'état final de l'affaire.
 *
 * Isolation tenant : `company_id` uuid NON nullable, en tête de tous les
 * index composés (scope tenant d'abord). Références pipeline/lead/owner par
 * uuid SANS FK (découplage inter-lots : tables #5708 / module CRM en cours).
 *
 * Archivage : softDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->uuid('pipeline_id')->nullable();
                $table->uuid('lead_id')->nullable();
                $table->uuid('owner_id')->nullable();

                $table->string('name', 255);
                $table->string('stage', 80)->default('prospection');
                $table->decimal('amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->date('expected_close_date')->nullable();
                // open | won | lost
                $table->string('status', 10)->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Index de requêtage pipeline/stage/owner/date (critère #5709).
                $table->index(['company_id', 'pipeline_id', 'stage'], 'crm_opp_company_pipeline_stage_idx');
                $table->index(['company_id', 'owner_id'], 'crm_opp_company_owner_idx');
                $table->index(['company_id', 'expected_close_date'], 'crm_opp_company_close_date_idx');
                $table->index(['company_id', 'status'], 'crm_opp_company_status_idx');
            });
        }

        $schema = resolveTableSchema('crm_opportunities');
        if ($schema !== null) {
            DB::statement("ALTER TABLE {$schema}.crm_opportunities ADD CONSTRAINT crm_opportunities_status_check CHECK (status IN ('open', 'won', 'lost'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
