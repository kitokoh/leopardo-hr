<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM client (tenant) — issue #5708 (CRM-V0-04) — table `crm_accounts`.
 *
 * Compte : organisation cliente gérée par l'entreprise (tenant). Chaque ligne
 * appartient à UN tenant (`company_id` uuid NON nullable, indexé) — isolation
 * via BelongsToCompany + search_path (ADR-CRM-003, #5706).
 *
 * Colonnes PII (email, phone, notes) : chiffrées au repos via
 * `SensitiveDataEncryptor` (préfixe `enc:`) à partir de #5713 (CRM-V0-09).
 * La stratégie HMAC de déduplication (hash déterministe `hmac:` sur email
 * normalisé pour les lookups sans clair) est documentée dans #5713 ; elle
 * n'ajoute pas de colonne ici — l'index unique de dédup relève de #5718.
 *
 * Archivage : soft delete via `archived_at` (jamais de DELETE destructif
 * côté API, purge RGPD encadrée).
 */
class CreateCrmAccountsTable extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 255);
                // Whitelist applicative : active|inactive|archived (validation #5711).
                $table->string('status', 20)->default('active');
                // Propriétaire (employé du tenant) — nullable (non assigné).
                $table->unsignedBigInteger('owner_id')->nullable();
                // PII — chiffrée au repos (#5713) ; text pour les valeurs enc:.
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index('company_id', 'crm_accounts_company_idx');
                $table->index(['company_id', 'status'], 'crm_accounts_company_status_idx');
                $table->index(['company_id', 'owner_id'], 'crm_accounts_company_owner_idx');

                $table->foreign('owner_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
}
