<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6067 (TRAVEL-415) — Contacts voyageurs & registre de consentement.
 *
 * Données de notification des clients de la verticale (spec §8.5) : jamais
 * d'envoi sans consentement explicite par canal. `travel_customer_contacts`
 * est la table de consentement propriétaire de la verticale — les canaux de
 * livraison restent ceux de la plateforme (in-app BC-13, email
 * transactionnel). Les consentements sont horodatés (traçabilité RGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_customer_contacts')) {
            return;
        }

        Schema::create('travel_customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();

            // Consentement explicite par canal (RGPD) — défaut : AUCUN envoi.
            $table->boolean('email_consent_given')->default(false);
            $table->timestampTz('email_consent_at')->nullable();
            $table->boolean('sms_consent_given')->default(false);
            $table->timestampTz('sms_consent_at')->nullable();
            $table->boolean('whatsapp_consent_given')->default(false);
            $table->timestampTz('whatsapp_consent_at')->nullable();

            $table->string('metadata_json', 2000)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['company_id', 'email'], 'travel_customer_contacts_company_email_unique');
            $table->unique(['company_id', 'phone'], 'travel_customer_contacts_company_phone_unique');
            $table->index(['company_id', 'email'], 'travel_customer_contacts_company_email_idx');
        });

        DB::statement("COMMENT ON TABLE travel_customer_contacts IS 'Contacts voyageurs et registre de consentement notification par canal (TRAVEL-415/#6067).'");
        DB::statement("COMMENT ON COLUMN travel_customer_contacts.email_consent_given IS 'Consentement email explicite (defaut: aucun envoi).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_customer_contacts');
    }
};
