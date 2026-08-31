<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture de test pour le module CRM client (issues #5719–#5722, batch V1).
 *
 * Les tables CRM V0 (`crm_accounts`, `crm_contacts`, ...) sont créées par les
 * migrations tenant des issues #5708/#5709/#5710 (fondation V0, mergée en
 * parallèle). Tant que ces migrations ne sont pas sur `main`, les tests V1 de
 * ce batch créent ici les tables manquantes avec le schéma canonique convenu
 * entre agents (commentaire de coordination sur #5708/#5709/#5710), puis ce
 * trait devient un no-op (garde `schemaTableExists`) une fois la fondation
 * mergée — les tests tournent alors contre les VRAIES migrations.
 *
 * Usage : `use CreatesCrmSchema;` + `$this->createCrmSchemaIfMissing();`
 * dans `setUp()` (après RefreshTenantDatabase).
 */
trait CreatesCrmSchema
{
    private function createCrmSchemaIfMissing(): void
    {
        if (! schemaTableExists('crm_accounts')) {
            // Schéma canonique retenu pour #5708 (PR #5757 — riche).
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 191);
                $table->string('legal_name', 191)->nullable();
                $table->string('industry', 100)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->char('country', 2)->nullable();
                $table->string('tax_id', 50)->nullable();
                $table->string('status', 20)->default('active');
                $table->string('source', 20)->default('manual');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'owner_id']);
                $table->index(['company_id', 'name']);
                $table->index(['company_id', 'email']);
            });
        }

        if (! schemaTableExists('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('first_name', 80)->nullable();
                $table->string('last_name', 80)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('job_title', 120)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'account_id']);
                $table->index(['company_id', 'email']);
                $table->index(['company_id', 'last_name']);
            });
        }

        if (! schemaTableExists('crm_pipelines')) {
            // Schéma réel #5709 (PR #5750).
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'crm_pipelines_company_name_unique');
            });
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
            });
        }

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

                $table->index(['company_id', 'pipeline_id']);
                $table->index(['owner_id']);
            });
        }

        if (! schemaTableExists('crm_activities')) {
            // Schéma réel #5710 (PR #5753) : append-only, occurred_at, created_by.
            Schema::create('crm_activities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('opportunity_id')->nullable();
                $table->string('type', 40);
                $table->string('subject', 200)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'account_id', 'occurred_at']);
            });
        }

        if (! schemaTableExists('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('opportunity_id')->nullable();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('todo');
                $table->string('priority', 10)->default('medium');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'due_at']);
                $table->index(['assigned_to']);
            });
        }

        if (! schemaTableExists('crm_task_reminders')) {
            // Issue #5720 — relances internes (table portée par ce batch).
            Schema::create('crm_task_reminders', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('task_id')->index();
                $table->date('remind_date');
                $table->timestampTz('created_at')->useCurrent();

                $table->unique(['task_id', 'remind_date'], 'crm_task_reminders_task_date_unique');
            });
        }
    }

    /**
     * Insère une ligne crm_accounts de test (schéma canonique, cf. #5708).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmAccount(array $overrides = []): int
    {
        return DB::table('crm_accounts')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Compte Alpha',
            'legal_name' => null,
            'industry' => 'logistics',
            'website' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'city' => null,
            'country' => null,
            'tax_id' => null,
            'status' => 'active',
            'source' => 'manual',
            'owner_id' => null,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Insère une ligne crm_contacts de test (schéma canonique, cf. #5708).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmContact(array $overrides = []): int
    {
        return DB::table('crm_contacts')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'account_id' => null,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'phone' => '+213550000000',
            'job_title' => null,
            'is_primary' => false,
            'status' => 'active',
            'notes' => null,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Insère une ligne crm_tasks de test (schéma canonique, cf. #5710).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmTask(array $overrides = []): int
    {
        return DB::table('crm_tasks')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'account_id' => null,
            'contact_id' => null,
            'lead_id' => null,
            'opportunity_id' => null,
            'title' => 'Relancer le client',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_at' => null,
            'completed_at' => null,
            'assigned_to' => null,
            'completed_by' => null,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Insère une ligne crm_activities de test (schéma canonique, cf. #5710).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmActivity(array $overrides = []): int
    {
        return DB::table('crm_activities')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'account_id' => 1,
            'contact_id' => null,
            'lead_id' => null,
            'opportunity_id' => null,
            'type' => 'call',
            'subject' => null,
            'description' => null,
            'occurred_at' => now(),
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
    /**
     * Insère une ligne crm_pipelines de test (schéma canonique, cf. #5709).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmPipeline(array $overrides = []): int
    {
        return DB::table('crm_pipelines')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Pipeline principal',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Insère une ligne crm_stages de test (schéma canonique, cf. #5709).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmStage(array $overrides = []): int
    {
        return DB::table('crm_pipeline_stages')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'pipeline_id' => 1,
            'name' => 'Prospection',
            'position' => 0,
            'color' => null,
            'is_won' => false,
            'is_lost' => false,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Insère une ligne crm_opportunities de test (schéma canonique, cf. #5709).
     *
     * @param  array<string, mixed>  $overrides
     * @return int
     */
    private function createCrmOpportunity(array $overrides = []): int
    {
        return DB::table('crm_opportunities')->insertGetId(array_merge([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'pipeline_id' => 1,
            'stage_id' => 1,
            'name' => 'Opportunité Alpha',
            'account_id' => null,
            'converted_from_lead_id' => null,
            'amount' => 10000,
            'currency' => 'DZD',
            'expected_close_date' => null,
            'owner_id' => null,
            'source' => null,
            'description' => null,
            'won_at' => null,
            'lost_at' => null,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

}

