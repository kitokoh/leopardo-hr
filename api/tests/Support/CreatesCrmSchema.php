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
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('name', 255);
                $table->string('legal_name', 255)->nullable();
                $table->string('industry', 120)->nullable();
                $table->string('website', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('owner_id')->nullable()->index();
                $table->timestampTz('archived_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'owner_id']);
            });
        }

        if (! schemaTableExists('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('first_name', 120);
                $table->string('last_name', 120);
                $table->string('email', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('owner_id')->nullable()->index();
                $table->string('job_title', 160)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestampTz('archived_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'account_id']);
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
            'description' => null,
            'status' => 'active',
            'owner_id' => null,
            'archived_at' => null,
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
            'is_primary' => false,
            'owner_id' => null,
            'job_title' => null,
            'status' => 'active',
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
