<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM client (tenant) — issue #5708 (CRM-V0-04) — table `crm_contacts`.
 *
 * Contact : personne rattachée à un compte (`crm_accounts`) — tenant-scopé
 * (`company_id` uuid NON nullable, indexé).
 *
 * Contrainte clé : **au plus un contact primaire par compte** — index unique
 * partiel `crm_contacts_primary_account_unique` sur (account_id) filtré
 * `WHERE is_primary = TRUE AND account_id IS NOT NULL` (les contacts non
 * rattachés, flux #5717, ne sont pas concernés).
 *
 * PII (email, phone, notes) : chiffrée au repos (#5713, préfixe `enc:`),
 * HMAC de dédup documenté dans #5713. Consentements de communication
 * (opt_in_email/sms/whatsapp) posés dès la création — exploités par #5722.
 *
 * Archivage : soft delete via `archived_at`.
 */
class CreateCrmContactsTable extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Rattachement au compte — nullable pendant le flux lead→compte
                // (#5717), l'index unique partiel gère les NULL.
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                // PII — chiffrée au repos (#5713).
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('title', 100)->nullable();
                // Whitelist applicative : active|inactive (validation #5711).
                $table->string('status', 20)->default('active');
                // Propriétaire (employé du tenant) — nullable.
                $table->unsignedBigInteger('owner_id')->nullable();
                // Contact primaire : au plus un par compte (index partiel).
                $table->boolean('is_primary')->default(false);
                // Consentements de communication (RGPD, #5722).
                $table->boolean('opt_in_email')->default(false);
                $table->boolean('opt_in_sms')->default(false);
                $table->boolean('opt_in_whatsapp')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index('company_id', 'crm_contacts_company_idx');
                $table->index(['company_id', 'status'], 'crm_contacts_company_status_idx');
                $table->index(['company_id', 'owner_id'], 'crm_contacts_company_owner_idx');
                $table->index(['company_id', 'account_id'], 'crm_contacts_company_account_idx');

                $table->foreign('account_id')
                    ->references('id')
                    ->on('crm_accounts')
                    ->cascadeOnDelete();
                $table->foreign('owner_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });

            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS crm_contacts_primary_account_unique
                 ON crm_contacts (account_id)
                 WHERE is_primary = TRUE AND account_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
}
