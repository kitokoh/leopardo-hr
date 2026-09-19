<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #7751 (contenu de campagne email).
 *
 * Ajout ADDITIF de `subject` + `body` sur `crm_campaigns` : une campagne
 * email « démarrée » n'envoyait rien d'exploitable (#5726 codait le message
 * en dur). Les deux colonnes restent nullables en base (les canaux sms/
 * whatsapp n'en ont pas besoin) — l'obligation pour le canal email est
 * appliquée par validation métier au start (`CampaignService::start`).
 *
 * Migration idempotente (gardes `schemaHasColumn`), aucune donnée modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_campaigns')) {
            return;
        }

        Schema::table('crm_campaigns', function (Blueprint $table): void {
            if (! schemaHasColumn('crm_campaigns', 'subject')) {
                $table->string('subject', 255)->nullable();
            }

            if (! schemaHasColumn('crm_campaigns', 'body')) {
                $table->text('body')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('crm_campaigns')) {
            return;
        }

        Schema::table('crm_campaigns', function (Blueprint $table): void {
            if (schemaHasColumn('crm_campaigns', 'subject')) {
                $table->dropColumn('subject');
            }

            if (schemaHasColumn('crm_campaigns', 'body')) {
                $table->dropColumn('body');
            }
        });
    }
};
