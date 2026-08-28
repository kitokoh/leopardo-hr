<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client (tenant) — Issue #5709 (CRM-V0-05).
 *
 * Tables tenant (`shared_tenants`) du CRM client Leopardo — leads, pipelines,
 * stages et opportunités — conformément à `docs/architecture/CRM_CLIENT_DONNEES.md`
 * et `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md` (CRM commercial
 * Platform/Marketing inchangé).
 *
 * Règles :
 *   - migration additive et idempotente (garde schemaTableExists par table) ;
 *   - company_id uuid NON nullable sur CHAQUE table — l'isolation tenant est
 *     portée par le trait BelongsToCompany (garde fail-closed #3727) et aucune
 *     relation cross-tenant n'est physiquement possible (chaque extrémité porte
 *     son propre company_id) ;
 *   - colonnes PII (email, phone) en `text` pour le cast `encrypted` (RGPD) ;
 *   - états contraints par CHECK nommés et documentés (CRM_CLIENT_DONNEES.md §2) ;
 *   - refs inter-tables optionnelles (`contact_id`) en colonne indexée SANS FK
 *     matérielle : la table `crm_contacts` est livrée par l'issue CRM-V0-04
 *     (#5708) ; la contrainte sera ajoutée par la suite CRM-V0-05+ une fois la
 *     table source mergée sur main.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! schemaTableExists('crm_pipeline_stages')) {
            Schema::create('crm_pipeline_stages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('pipeline_id');
                $table->string('name', 120);
                $table->integer('position')->default(0);
                // Probabilité de conversion attendue du stage (0-100, CHECK).
                $table->unsignedTinyInteger('probability')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('color', 20)->nullable();
                $table->timestamps();

                $table->foreign('pipeline_id')->references('id')->on('crm_pipelines')->cascadeOnDelete();
                $table->unique(['pipeline_id', 'name']);
                $table->unique(['pipeline_id', 'position']);
                $table->index(['company_id', 'is_active']);
            });

            DB::statement(
                'ALTER TABLE crm_pipeline_stages ADD CONSTRAINT crm_pipeline_stages_probability_check '
                .'CHECK (probability BETWEEN 0 AND 100)'
            );
        }

        if (! schemaTableExists('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('first_name', 120);
                $table->string('last_name', 120);
                // PII chiffrée au repos (cast encrypted).
                $table->text('email')->nullable();
                $table->text('phone')->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('job_title', 120)->nullable();
                // manual | referral | website | social | email | call | event | partner | other
                $table->string('source', 30)->default('manual');
                // new | contacted | qualified | proposal | won | lost | junk
                $table->string('status', 30)->default('new');
                $table->unsignedBigInteger('pipeline_id')->nullable();
                $table->unsignedBigInteger('stage_id')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->decimal('expected_value', 15, 2)->default(0);
                $table->string('currency', 10)->nullable();
                $table->text('notes')->nullable();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->foreign('pipeline_id')->references('id')->on('crm_pipelines')->nullOnDelete();
                $table->foreign('stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'owner_id']);
                $table->index(['company_id', 'pipeline_id', 'stage_id']);
                $table->index(['company_id', 'created_at']);
                $table->index(['company_id', 'last_activity_at']);
            });

            DB::statement(
                'ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_status_check '
                ."CHECK (status IN ('new','contacted','qualified','proposal','won','lost','junk'))"
            );
            DB::statement(
                'ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_source_check '
                ."CHECK (source IN ('manual','referral','website','social','email','call','event','partner','other'))"
            );
        }

        if (! schemaTableExists('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 255);
                $table->unsignedBigInteger('lead_id')->nullable();
                // Référence logique vers crm_contacts (livrée par #5708) — index
                // seule tant que la table source n'est pas sur main.
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('currency', 10)->nullable();
                // open | won | lost
                $table->string('status', 20)->default('open');
                $table->unsignedBigInteger('pipeline_id')->nullable();
                $table->unsignedBigInteger('stage_id')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->date('expected_close_date')->nullable();
                $table->unsignedTinyInteger('win_probability')->default(0);
                $table->text('notes')->nullable();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('crm_leads')->nullOnDelete();
                $table->foreign('pipeline_id')->references('id')->on('crm_pipelines')->nullOnDelete();
                $table->foreign('stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'owner_id']);
                $table->index(['company_id', 'pipeline_id', 'stage_id']);
                $table->index(['company_id', 'expected_close_date']);
                $table->index(['company_id', 'created_at']);
                $table->index(['company_id', 'last_activity_at']);
            });

            DB::statement(
                'ALTER TABLE crm_opportunities ADD CONSTRAINT crm_opportunities_status_check '
                ."CHECK (status IN ('open','won','lost'))"
            );
            DB::statement(
                'ALTER TABLE crm_opportunities ADD CONSTRAINT crm_opportunities_probability_check '
                .'CHECK (win_probability BETWEEN 0 AND 100)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
