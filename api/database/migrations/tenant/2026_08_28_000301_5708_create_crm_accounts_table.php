<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM client — Issue #5708 (CRM-V0-04).
 *
 * crm_accounts : comptes (entreprises/organisations clientes) du CRM
 * tenant. Isolation stricte via `company_id` uuid NON nullable
 * (BelongsToCompany) ; archivage par statut borné (CHECK) ; `owner_id`
 * pointe un employé du tenant (bigint, PK `employees.id`).
 *
 * PII : `email` reste en clair en V0 (recherche/dédup #5718/#5719) ;
 * `phone` et `tax_id` sont chiffrés au repos (casts `encrypted` sur les
 * modèles, pattern `AccountingContact`). La stratégie HMAC / registre RGPD
 * est portée par l'issue #5713 (CRM-V0-09) — voir la doc de migration
 * `docs/architecture/CRM_CLIENT_DONNEES.md` §PII.
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles (contacts) — une référence
 *     cross-tenant est une violation FK en base ;
 *   - CHECK `status` (active|inactive|archived) et `source`
 *     (manual|import|web|referral|other) — valeurs inconnues rejetées ;
 *   - indexes scope/status/owner/name/created pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 191);
                $table->string('legal_name', 191)->nullable();
                $table->string('industry', 100)->nullable();
                $table->string('website', 255)->nullable();
                // PII — chiffré au repos (cast `encrypted`), recherche/dédup V1.
                $table->string('email', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->char('country', 2)->nullable();
                // PII fiscale — chiffré au repos (cast `encrypted`).
                $table->string('tax_id', 50)->nullable();
                // active | inactive | archived — CHECK crm_accounts_status_check
                $table->string('status', 20)->default('active');
                // manual | import | web | referral | other — CHECK crm_accounts_source_check
                $table->string('source', 20)->default('manual');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id) des
                // tables filles : rend toute référence cross-tenant impossible.
                $table->unique(['id', 'company_id'], 'crm_accounts_id_company_unique');
                $table->index(['company_id', 'status'], 'crm_accounts_company_status_idx');
                $table->index(['company_id', 'owner_id'], 'crm_accounts_company_owner_idx');
                $table->index(['company_id', 'name'], 'crm_accounts_company_name_idx');
                $table->index(['company_id', 'email'], 'crm_accounts_company_email_idx');
                $table->index(['company_id', 'created_at'], 'crm_accounts_company_created_idx');
            });

            $schema = resolveTableSchema('crm_accounts');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'crm_accounts_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"crm_accounts\" ADD CONSTRAINT crm_accounts_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'crm_accounts_source_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"crm_accounts\" ADD CONSTRAINT crm_accounts_source_check "
                    ."CHECK (source IN ('manual','import','web','referral','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
};
