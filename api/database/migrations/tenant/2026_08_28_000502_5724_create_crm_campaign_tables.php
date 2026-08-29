<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #5724 (campagnes marketing tenant).
 *
 * - crm_campaigns : campagne tenant-scopée (owner créateur), cible un
 *   segment (#5723) OU une audience explicite (snapshot), statuts
 *   draft|scheduled|running|paused|finished|cancelled, démarrable/
 *   stoppable (pause/resume/cancel) et observable (report par statut) ;
 * - crm_campaign_sends : envois unitaires par contact — statuts
 *   pending|queued|sent|failed|bounced|cancelled|suppressed, lien
 *   provider_message_id (interopérabilité canaux #5726), audités.
 *
 * `contact_id`/`segment_id` restent des entiers indexés sans FK vers les
 * tables CRM (livrées par #5708/#5723) — isolation par company_id +
 * BelongsToCompany.
 *
 * Migration additive et idempotente (garde schemaTableExists) ; company_id
 * uuid NON nullable — isolation tenant via BelongsToCompany.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_campaigns')) {
            Schema::create('crm_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 120);
                $table->string('description', 255)->nullable();
                // Canal : email | sms | whatsapp.
                $table->string('channel', 20);
                // draft | scheduled | running | paused | finished | cancelled.
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('segment_id')->nullable();
                // Snapshot d'audience : liste de contact_id (après filtre
                // consentement au start).
                $table->jsonb('audience_snapshot')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_campaigns_company_status_idx');
                $table->index(['company_id', 'segment_id'], 'crm_campaigns_company_segment_idx');
            });
        }

        if (! schemaTableExists('crm_campaign_sends')) {
            Schema::create('crm_campaign_sends', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('campaign_id')->index();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('contact_id');
                $table->string('channel', 20);
                // pending | queued | sent | failed | bounced | cancelled | suppressed.
                $table->string('status', 20)->default('pending');
                $table->string('provider_message_id', 255)->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status'], 'crm_campaign_sends_campaign_status_idx');
                $table->index(['company_id', 'contact_id'], 'crm_campaign_sends_company_contact_idx');
                $table->index(['company_id', 'status'], 'crm_campaign_sends_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_sends');
        Schema::dropIfExists('crm_campaigns');
    }
};
