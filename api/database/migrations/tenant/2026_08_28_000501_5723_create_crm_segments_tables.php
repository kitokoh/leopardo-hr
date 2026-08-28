<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #5723 (segments CRM tenant simples).
 *
 * - crm_segments : définition versionnée d'un segment (JSONB, grammaire
 *   allowlistée — aucun SQL utilisateur), statut actif/inactif ;
 * - crm_segment_versions : historique des définitions (reproductibilité) ;
 * - crm_segment_members : appartenance tenant-scoped, source `computed`
 *   (rebuild depuis la définition) ou `manual` (ajout explicite) ; le
 *   rebuild remplace les membres computed, jamais les manuels.
 *
 * `contact_id` reste un entier indexé sans FK vers crm_contacts (livrée par
 * #5708) — l'isolation est portée par company_id + BelongsToCompany.
 *
 * Migration additive et idempotente (garde schemaTableExists) ; company_id
 * uuid NON nullable — isolation tenant via BelongsToCompany.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_segments')) {
            Schema::create('crm_segments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 120);
                $table->string('description', 255)->nullable();
                // Définition JSONB : {"operator":"and|or","conditions":[...]}.
                $table->jsonb('definition');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'crm_segments_company_name_unique');
                $table->index(['company_id', 'is_active'], 'crm_segments_company_active_idx');
            });
        }

        if (! schemaTableExists('crm_segment_versions')) {
            Schema::create('crm_segment_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('segment_id');
                $table->unsignedInteger('version');
                $table->jsonb('definition');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('changed_at');
                $table->timestamps();

                $table->unique(['segment_id', 'version'], 'crm_segment_versions_segment_version_unique');
            });
        }

        if (! schemaTableExists('crm_segment_members')) {
            Schema::create('crm_segment_members', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('segment_id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('contact_id');
                // computed (rebuild) | manual (ajout explicite).
                $table->string('source', 20)->default('computed');
                $table->timestamp('built_at')->nullable();
                $table->timestamps();

                $table->unique(['segment_id', 'contact_id'], 'crm_segment_members_segment_contact_unique');
                $table->index(['company_id', 'contact_id'], 'crm_segment_members_company_contact_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_segment_members');
        Schema::dropIfExists('crm_segment_versions');
        Schema::dropIfExists('crm_segments');
    }
};
