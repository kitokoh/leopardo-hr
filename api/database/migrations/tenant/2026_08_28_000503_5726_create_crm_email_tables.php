<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #5726 (email transactionnel et marketing contrôlé).
 *
 * - crm_email_suppressions : blocage d'adresse (bounce/complaint/
 *   désabonnement/manuel) — email stocké UNIQUEMENT sous forme de hash
 *   SHA-256 (aucune PII en clair, recherche exacte par hash) ;
 * - crm_email_events : journal des événements provider (sent/delivered/
 *   bounced/complained/opened/clicked/unsubscribed) lié au
 *   provider_message_id de l'envoi de campagne (#5724) — observabilité et
 *   preuve d'envoi RGPD.
 *
 * company_id uuid NON nullable — isolation tenant via BelongsToCompany /
 * requêtes tenant-scopées ; migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_email_suppressions')) {
            Schema::create('crm_email_suppressions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable();
                // SHA-256 hex de l'adresse normalisée — jamais l'adresse en clair.
                $table->char('email_hash', 64);
                // bounce | complaint | unsubscribe | manual.
                $table->string('reason', 20);
                $table->string('source', 30)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'email_hash'], 'crm_email_suppressions_company_hash_unique');
                $table->index(['company_id', 'reason'], 'crm_email_suppressions_company_reason_idx');
            });
        }

        if (! schemaTableExists('crm_email_events')) {
            Schema::create('crm_email_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('send_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('provider_message_id', 255)->nullable();
                // sent | delivered | bounced | complained | opened | clicked | unsubscribed.
                $table->string('event', 20);
                $table->jsonb('payload')->nullable();
                $table->timestamp('received_at');
                $table->timestamps();

                $table->index(['company_id', 'event'], 'crm_email_events_company_event_idx');
                $table->index(['provider_message_id'], 'crm_email_events_provider_message_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_email_events');
        Schema::dropIfExists('crm_email_suppressions');
    }
};
