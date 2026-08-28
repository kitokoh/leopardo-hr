<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #5722 (consentements et préférences de communication).
 *
 * Table d'état courant des consentements CRM (tenant-scoped) :
 *   - une ligne par (company_id, contact_id, canal, finalité) = état courant ;
 *   - l'historique immuable vit dans `audit_logs` (auditable CrmConsent,
 *     actions consent.granted / consent.denied / consent.withdrawn) ;
 *   - le retrait (`withdrawn`) propage l'événement CrmConsentRevoked aux
 *     campagnes et providers (aucun envoi sans consentement requis).
 *
 * `contact_id` reste un simple entier indexé (pas de FK vers crm_contacts) :
 * la table crm_contacts est livrée par l'issue #5708 (CRM V0-04) — l'isolation
 * est portée par company_id + le trait BelongsToCompany, jamais par FK.
 *
 * Migration additive et idempotente (garde schemaTableExists) ; company_id
 * uuid NON nullable — isolation tenant via BelongsToCompany.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_consents')) {
            Schema::create('crm_consents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('contact_id');
                // Canal : email | sms | whatsapp | phone | push.
                $table->string('channel', 20);
                // Finalité : marketing | transactional.
                $table->string('purpose', 20);
                // État courant : granted | denied | withdrawn.
                $table->string('status', 20);
                // Origine : form | api | import | manual | email_link.
                $table->string('source', 30);
                $table->string('source_ref', 255)->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                // Un seul état courant par (tenant, contact, canal, finalité).
                $table->unique(
                    ['company_id', 'contact_id', 'channel', 'purpose'],
                    'crm_consents_contact_channel_purpose_unique');
                $table->index(['company_id', 'status'], 'crm_consents_company_status_idx');
                $table->index(['company_id', 'contact_id'], 'crm_consents_company_contact_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_consents');
    }
};
