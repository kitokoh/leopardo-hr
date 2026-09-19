<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7764 (spec MISSION_ESPACE_CLIENT §3.4) — grand livre des crédits IA.
 *
 * `ai_credit_ledger` porte TOUS les mouvements de tokens IA achetés d'un
 * tenant : achats (`delta > 0`, reason `purchase`), consommation (`delta < 0`,
 * reason `consumption`) et ajustements manuels (`adjustment`). Le solde d'une
 * entreprise est la somme des `delta` — aucune table de solde matérialisée
 * (source de vérité unique, pas de dérive).
 *
 * Idempotence des achats : `reference` porte l'id de session Stripe (ou la
 * clé sandbox) et un index unique PARTIEL `(reference) WHERE reason =
 * 'purchase' AND reference IS NOT NULL` verrouille au niveau base qu'un rejeu
 * de webhook `checkout.session.completed` ne crédite jamais deux fois — en
 * complément du registre `webhook_events` (#5444).
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, aucune FK vers
 * `public.companies` (conventions migrations tenant §2.6). Migration
 * réentrante (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('ai_credit_ledger')) {
            return;
        }

        Schema::create('ai_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // Mouvement signé en TOKENS (achat +, consommation −).
            $table->bigInteger('delta');
            $table->string('reason', 20); // purchase|consumption|adjustment
            // Id de session Stripe / clé d'idempotence sandbox (achats).
            $table->string('reference', 191)->nullable();
            // Qui a déclenché le mouvement (achat principal, ajustement admin) —
            // nullable : la consommation et les webhooks n'ont pas d'acteur employé.
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            // « Historique / solde de CE tenant » — la question de l'API et du service.
            $table->index(['company_id', 'created_at'], 'ai_credit_ledger_company_created_idx');
        });

        // Index unique partiel : une référence d'achat ne crédite qu'UNE fois
        // (idempotence webhook au niveau base, PostgreSQL only comme la CI).
        if (DB::getDriverName() === 'pgsql') {
            $schema = resolveTableSchema('ai_credit_ledger');
            if ($schema !== null) {
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS ai_credit_ledger_purchase_reference_unique '
                    ."ON {$schema}.ai_credit_ledger (reference) "
                    ."WHERE reason = 'purchase' AND reference IS NOT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ai_credit_ledger_purchase_reference_unique');
        }

        Schema::dropIfExists('ai_credit_ledger');
    }
};
