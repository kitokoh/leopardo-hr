<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6067 (TRAVEL-415) — Notifications voyageur (canaux BC-13 + consentement).
 *
 * - `travel_notification_consents` : consentement RGPD explicite par
 *   (tenant, contact, canal). Aucune notification n'est émise sans consentement
 *   actif ET canal configuré (critère d'acceptation).
 * - `travel_notification_logs` : journal d'audit de chaque tentative
 *   (sent/skipped/failed) avec payload redacted — traçabilité RGPD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_notification_consents')) {
            Schema::create('travel_notification_consents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('contact_identifier', 255);
                $table->string('channel', 20);
                $table->string('source', 40)->default('booking');
                $table->timestamp('granted_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'contact_identifier', 'channel'],
                    'travel_notif_consent_company_contact_channel_unique',
                );
                $table->index(
                    ['company_id', 'contact_identifier'],
                    'travel_notif_consent_company_contact_idx',
                );
            });
        }

        if (! schemaTableExists('travel_notification_logs')) {
            Schema::create('travel_notification_logs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('event_id')->nullable();
                $table->string('event_type', 60);
                $table->string('contact_identifier', 255);
                $table->string('channel', 20);
                $table->string('status', 20);
                $table->string('reason', 500)->nullable();
                $table->json('payload_redacted')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['company_id', 'event_id'], 'travel_notif_logs_company_event_idx');
                $table->index(['company_id', 'status'], 'travel_notif_logs_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_notification_logs');
        Schema::dropIfExists('travel_notification_consents');
    }
};
