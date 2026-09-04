<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5725 — Lookup public des webhooks WhatsApp Business.
 *
 * Les webhooks fournisseur arrivent SANS session tenant (route publique,
 * signature HMAC). Cette table `public` mappe provider_key (ex.
 * phone_number_id WhatsApp) → (company_id, channel_id) pour résoudre le
 * tenant avant de traiter l'événement sous `TenantManager::withinTenant()`.
 *
 * Idempotente (rejeu Render) : création conditionnelle + noms qualifiés
 * (pattern F-17). Le champ `provider_key` est le seul secret de routage ;
 * aucun token ne vit ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_webhook_channel_lookup')) {
            Schema::create('public.crm_webhook_channel_lookup', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('channel_id')->unique();
                $table->string('provider', 40);
                $table->string('provider_key', 160)->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.crm_webhook_channel_lookup');
    }
};
