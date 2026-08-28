<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5725 / #5727 — Canaux de communication CRM tenant (WhatsApp Business,
 * SMS, email par adaptateur).
 *
 * Trois tables tenant-scoped :
 *   - `crm_channels` : registre des canaux configurés par tenant
 *     (type + provider, statut, quota mensuel, usage observé). Les secrets
 *     (tokens) ne vivent JAMAIS ici — uniquement en secret manager / env.
 *   - `crm_channel_conversations` : conversations par canal (inbox unique
 *     par provider_conversation_id).
 *   - `crm_channel_messages` : messages outbound/inbound, statut de
 *     livraison, erreurs, coût, dead-letter.
 *
 * PII : `to_address` / `from_address` / `body` sont chiffrés au repos via
 * les casts `encrypted` des modèles (convention CRM #5713 : toute donnée
 * personnelle est chiffrée, jamais en clair dans les Resources).
 *
 * Idempotence webhook : UNIQUE partiel (company_id, provider_message_id)
 * quand provider_message_id est non nul — un rejeu de webhook est absorbé.
 *
 * Conventions : uuid PK, `company_id` uuid non nullable indexé, timestamps,
 * garde schemaTableExists() (#1613), archivage soft via `archived_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_channels')) {
            Schema::create('crm_channels', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('type', 20);                       // whatsapp|sms|email
                $table->string('provider', 60);                   // ex. whatsapp_cloud_api|sms_audit
                $table->string('status', 20)->default('inactive'); // active|inactive|error
                $table->boolean('is_configured')->default(false);
                $table->unsignedInteger('monthly_quota')->nullable(); // null = illimité
                $table->unsignedInteger('used_this_month')->default(0);
                $table->string('quota_period', 7)->nullable();    // ex. "2026-08"
                $table->json('settings')->nullable();             // config non sensible (numéro, template refs)
                $table->string('last_error_message', 255)->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'type', 'provider'], 'crm_channels_company_type_provider_unique');
            });
        }

        if (! schemaTableExists('crm_channel_conversations')) {
            Schema::create('crm_channel_conversations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('channel_id')->index();
                $table->string('provider_conversation_id', 160)->nullable();
                $table->string('contact_ref_type', 20)->nullable(); // account|contact|lead
                $table->uuid('contact_ref_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->string('status', 20)->default('open');     // open|closed
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'provider_conversation_id'], 'crm_convs_company_provider_unique');
            });
        }

        if (! schemaTableExists('crm_channel_messages')) {
            Schema::create('crm_channel_messages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('channel_id')->index();
                $table->uuid('conversation_id')->nullable()->index();
                $table->string('provider', 60);
                $table->string('provider_message_id', 160)->nullable();
                $table->string('direction', 10)->default('outbound'); // outbound|inbound
                $table->string('to_address', 255)->nullable();        // chiffré (cast encrypted)
                $table->string('from_address', 255)->nullable();      // chiffré (cast encrypted)
                $table->text('body')->nullable();                     // chiffré (cast encrypted)
                $table->string('template_name', 100)->nullable();
                $table->string('status', 20)->default('queued');      // queued|sent|delivered|read|failed|dead_lettered
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(3);
                $table->string('error_code', 60)->nullable();
                $table->text('error_message')->nullable();
                $table->decimal('cost', 12, 4)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_messages_company_status_index');
                $table->index(['company_id', 'direction'], 'crm_messages_company_direction_index');
                $table->unique(['company_id', 'provider_message_id'], 'crm_messages_company_provider_msg_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_channel_messages');
        Schema::dropIfExists('crm_channel_conversations');
        Schema::dropIfExists('crm_channels');
    }
};
