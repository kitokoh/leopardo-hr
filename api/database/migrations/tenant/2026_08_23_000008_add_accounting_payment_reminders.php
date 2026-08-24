<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5229 (Phase B : paiements + relances).
 *
 * - accounting_payment_reminders : relances automatiques par (document, stage
 *   J+7/J+15/J+30) — l'unicité (company_id, document_id, stage) garantit
 *   « relances sans doublon » (DoD #5229), même en cas de double exécution.
 * - accounting_settings.payment_reminder_days : délais personnalisables par
 *   entreprise (json, défaut [7, 15, 30]).
 *
 * Migration additive et idempotente (garde schemaTableExists) ; numérotation
 * 000004 pour éviter toute collision avec 000001/000002/000003 portées par
 * les branches #5223/#5225/#5234 (non mergées sur main à l'écriture).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_payment_reminders')) {
            Schema::create('accounting_payment_reminders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('document_id');
                // Étape de relance : 1 = J+7, 2 = J+15, 3 = J+30 (index du jour configuré).
                $table->unsignedSmallInteger('stage');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'document_id', 'stage'], 'payment_reminder_stage_unique');
                $table->index(['company_id', 'document_id']);
            });
        }

        if (! Schema::hasColumn('accounting_settings', 'payment_reminder_days')) {
            Schema::table('accounting_settings', function (Blueprint $table): void {
                // Délais de relance en jours — json [7, 15, 30] par défaut (cast array).
                $table->json('payment_reminder_days')->nullable()->after('document_language');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payment_reminders');
        if (Schema::hasColumn('accounting_settings', 'payment_reminder_days')) {
            Schema::table('accounting_settings', function (Blueprint $table): void {
                $table->dropColumn('payment_reminder_days');
            });
        }
    }
};
