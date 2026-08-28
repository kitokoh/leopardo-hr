<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM V0 : table tenant-scoped des leads client.
 *
 * Le CRM client (espaces client / API tenant) est distinct du CRM commercial
 * Leopardo (Platform/Marketing, schéma public) — voir ADR-CRM-DUAL-CONTEXTS.
 *
 * Isolation tenant :
 * - `company_id` uuid NON nullable, indexé — toute donnée client appartient
 *   à une entreprise ; le filtrage cross-tenant est garanti par
 *   BelongsToCompany + Policies (aucune requête sans tenant).
 * - Aucune relation cross-tenant possible : pas de FK vers des tables hors
 *   périmètre, les références d'owner (users) sont par uuid sans FK.
 *
 * PII : email/phone sont des données personnelles. La stratégie HMAC
 * (lookup exact irréversible) et le chiffrement AES-256 au repos sont
 * documentés dans docs/security/CRM_PII_HMAC.md (issue #5713) — les
 * colonnes restent en clair pour l'exploitation tant que la stratégie
 * n'est pas appliquée (consolidation programmée avec le module CRM).
 *
 * Archivage : softDeletes (une donnée client archivée reste récupérable
 * sous contrôle, et l'audit RGPD exige la traçabilité des suppressions).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Rattachement futur à un compte (#5708) — colonne uuid sans
                // FK tant que les tables accounts/contacts ne sont pas livrées
                // (découplage : aucune relation cross-tenant possible).
                $table->uuid('account_id')->nullable();
                // Owner (utilisateur) — uuid sans FK, résolu par l'application.
                $table->uuid('owner_id')->nullable();

                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                // PII — voir docs/security/CRM_PII_HMAC.md (#5713).
                $table->string('email', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('title', 255)->nullable();
                // web | referral | import | manual | other
                $table->string('source', 20)->default('manual');
                // new | contacted | qualified | converted | rejected
                $table->string('status', 20)->default('new');
                $table->unsignedSmallInteger('score')->default(0);
                $table->json('tags')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Index de requêtage : scope tenant d'abord (préfixe company_id).
                $table->index(['company_id', 'status'], 'crm_leads_company_status_idx');
                $table->index(['company_id', 'owner_id'], 'crm_leads_company_owner_idx');
                $table->index(['company_id', 'created_at'], 'crm_leads_company_created_idx');
                $table->index(['company_id', 'email'], 'crm_leads_company_email_idx');
            });
        }

        // Contraintes de domaine CHECK (PostgreSQL — pattern DB::statement du
        // repo, cf. backfill #5588). Résolution du schéma via search_path.
        $schema = resolveTableSchema('crm_leads');
        if ($schema !== null) {
            $qualified = "{$schema}.crm_leads";
            DB::statement("ALTER TABLE {$qualified} ADD CONSTRAINT crm_leads_status_check CHECK (status IN ('new', 'contacted', 'qualified', 'converted', 'rejected'))");
            DB::statement("ALTER TABLE {$qualified} ADD CONSTRAINT crm_leads_source_check CHECK (source IN ('web', 'referral', 'import', 'manual', 'other'))");
            DB::statement("ALTER TABLE {$qualified} ADD CONSTRAINT crm_leads_score_check CHECK (score BETWEEN 0 AND 100)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
