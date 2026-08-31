<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5728 — Automatisations CRM (tenants).
 *
 * `crm_automations` : règles event/conditions/actions versionnées et
 * bornées (whitelists strictes côté code) avec statut contrôlé.
 * `crm_automation_runs` : historique d'exécution, idempotence par
 * `run_key` (unique par tenant), tentatives bornées, dead-letter,
 * simulation (`dry_run`) sans effet de bord.
 * `crm_automation_states` : interrupteur d'urgence par tenant.
 *
 * Conventions : uuid PK, `company_id` non nullable indexé, timestamps,
 * garde schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_automations')) {
            Schema::create('crm_automations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('name', 160);
                $table->string('trigger_event', 80);                 // whitelist code (ex. crm.lead.created)
                $table->json('conditions')->nullable();              // [{field, operator, value}]
                $table->json('actions');                             // [{type, config}]
                $table->string('status', 20)->default('draft');      // draft|active|paused|disabled
                $table->unsignedInteger('version')->default(1);
                $table->uuid('created_by')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_automations_company_status_index');
                $table->index(['company_id', 'trigger_event'], 'crm_automations_company_trigger_index');
            });
        }

        if (! schemaTableExists('crm_automation_runs')) {
            Schema::create('crm_automation_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('automation_id')->index();
                $table->string('trigger_event', 80);
                $table->string('entity_type', 40)->nullable();
                $table->string('entity_id', 64)->nullable();
                $table->string('run_key', 160);                     // hash déterministe (idempotence)
                $table->json('conditions_snapshot')->nullable();
                $table->json('actions_snapshot')->nullable();
                $table->string('status', 20)->default('pending');   // pending|succeeded|failed|skipped|dead_lettered
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(1);
                $table->boolean('dry_run')->default(false);
                $table->text('error')->nullable();
                $table->timestamp('ran_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'run_key'], 'crm_automation_runs_company_run_key_unique');
            });
        }

        if (! schemaTableExists('crm_automation_states')) {
            Schema::create('crm_automation_states', function (Blueprint $table): void {
                $table->uuid('company_id')->primary();
                $table->boolean('enabled')->default(true);
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_automation_states');
        Schema::dropIfExists('crm_automation_runs');
        Schema::dropIfExists('crm_automations');
    }
};
