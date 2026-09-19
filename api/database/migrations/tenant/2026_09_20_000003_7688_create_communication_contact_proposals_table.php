<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7688 (R3 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.3)
 * — propositions de creation de contact CRM : un expediteur INCONNU du CRM
 * n'est JAMAIS cree silencieusement ; la classification enregistre une
 * proposition que le proprietaire de la boite accepte (creation `crm_contacts`
 * via le contrat partage `App\Shared\Contracts\Crm`) ou ecarte.
 *
 * - `email` : adresse nue de l'expediteur (minimisation R2 — pas de display
 *   name fiable, `suggested_name` derive de la partie locale) ;
 * - `status` : `proposed` | `accepted` | `dismissed` ;
 * - `message_count` : nombre de messages recus de cet expediteur (tri UI) ;
 * - `crm_contact_id` : pose a l'acceptation (pas de FK inter-BC, #5584) ;
 * - UNIQUE (company_id, integration_id, email) : une proposition par
 *   expediteur et par boite, upsert idempotent a la re-sync.
 *
 * Isolation : `company_id` uuid indexe, FK integration cascade (purge R1/R2).
 * Migration reentrante (`schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('communication_contact_proposals')) {
            return;
        }

        Schema::create('communication_contact_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->uuid('integration_id');
            $table->string('email', 320);
            $table->string('suggested_name', 128)->nullable();
            $table->string('status', 16)->default('proposed')->index();
            $table->unsignedInteger('message_count')->default(1);
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamps();

            $table->foreign('integration_id')
                ->references('id')
                ->on('communication_integrations')
                ->cascadeOnDelete();

            $table->unique(
                ['company_id', 'integration_id', 'email'],
                'communication_contact_proposals_company_box_email_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_contact_proposals');
    }
};
