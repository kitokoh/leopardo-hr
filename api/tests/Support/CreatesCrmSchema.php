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
}
