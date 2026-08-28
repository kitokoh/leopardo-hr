<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM client — Issue #5708 (CRM-V0-04).
 *
 * crm_contacts : contacts (personnes) rattachés à un compte du CRM tenant.
 * Un contact appartient TOUJOURS à un compte (`account_id` NON nullable) —
 * la FK composite (account_id, company_id) rend toute relation
 * cross-tenant structurellement impossible.
 *
 * Contact primaire : au plus UN contact `is_primary` par (company_id,
 * account_id) — index unique partiel (PostgreSQL) ; basculer le primaire
 * passe par une transaction (V0 : application, #5711).
 *
 * PII : `email` en clair (recherche), `phone` chiffré au repos (cast
 * `encrypted`) ; stratégie HMAC/RGPD portée par l'issue #5713.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('account_id');
                $table->string('first_name', 80)->nullable();
                $table->string('last_name', 80)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('job_title', 120)->nullable();
                // Au plus un contact primaire par compte (index partiel ci-dessous).
                $table->boolean('is_primary')->default(false);
                // active | inactive | archived — CHECK crm_contacts_status_check
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'account_id'], 'crm_contacts_company_account_idx');
                $table->index(['company_id', 'account_id', 'status'], 'crm_contacts_company_account_status_idx');
                $table->index(['company_id', 'email'], 'crm_contacts_company_email_idx');
                $table->index(['company_id', 'last_name'], 'crm_contacts_company_last_name_idx');
                $table->index(['company_id', 'created_at'], 'crm_contacts_company_created_idx');

                // Cross-tenant impossible : la paire (account_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['account_id', 'company_id'], 'crm_contacts_account_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('crm_accounts')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('crm_contacts');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'crm_contacts_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"crm_contacts\" ADD CONSTRAINT crm_contacts_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
                // Contact primaire unique par compte : UNIQUE partiel
                // (company_id, account_id) WHERE is_primary.
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'crm_contacts_one_primary_account_idx') "
                    ."THEN CREATE UNIQUE INDEX crm_contacts_one_primary_account_idx ON \"{$schema}\".\"crm_contacts\" "
                    ."(company_id, account_id) WHERE is_primary; END IF; END $$"
                );

                // FK additive (account_id, company_id) sur crm_opportunities
                // (table livrée par #5709, lot parallèle) : référence au compte
                // du MÊME tenant uniquement. Garde schemaTableExists — sans
                // effet si la table n'est pas encore migrée.
                if (schemaTableExists('crm_opportunities')) {
                    DB::statement(
                        "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'crm_opportunities_account_company_fk') "
                        ."THEN ALTER TABLE \"{$schema}\".\"crm_opportunities\" "
                        ."ADD CONSTRAINT crm_opportunities_account_company_fk "
                        ."FOREIGN KEY (account_id, company_id) REFERENCES \"{$schema}\".\"crm_accounts\"(id, company_id); END IF; END $$"
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
