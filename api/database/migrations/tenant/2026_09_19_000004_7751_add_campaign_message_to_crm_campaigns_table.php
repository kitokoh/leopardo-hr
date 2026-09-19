<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module CRM client — Issue #7751 (contenu de campagne email).
 *
 * Ajoute le message porté par la campagne : `subject` + `body`. Jusqu'ici
 * `CrmEmailService::sendCampaignSend()` envoyait un contenu codé en dur
 * (« Campagne CRM {id} ») — une campagne email doit porter son propre
 * message, validé au start (canal email).
 *
 * Migration additive et idempotente (gardes schemaTableExists/hasColumn) —
 * aucune nouvelle table (règle « une table, une migration » respectée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_campaigns')) {
            return;
        }

        Schema::table('crm_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_campaigns', 'subject')) {
                $table->string('subject', 255)->nullable();
            }

            if (! Schema::hasColumn('crm_campaigns', 'body')) {
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
            if (Schema::hasColumn('crm_campaigns', 'subject')) {
                $table->dropColumn('subject');
            }

            if (Schema::hasColumn('crm_campaigns', 'body')) {
                $table->dropColumn('body');
            }
        });
    }
};
