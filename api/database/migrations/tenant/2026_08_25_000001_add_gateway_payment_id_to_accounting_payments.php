<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5272 — Paiement en ligne : identifiant passerelle sur accounting_payments.
 *
 * `gateway_payment_id` (nullable) porte l'identifiant externe de la session de
 * paiement (checkout id Chargily / Stripe). Index UNIQUE (company_id,
 * gateway_payment_id) : c'est la clé d'idempotence du webhook — un rejeu de la
 * passerelle ne crée jamais de double paiement.
 *
 * Migration additive + idempotente (garde #1962) : colonne gardée par
 * schemaHasColumn, index via CREATE UNIQUE INDEX IF NOT EXISTS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaHasColumn('accounting_payments', 'gateway_payment_id')) {
            Schema::table('accounting_payments', function (Blueprint $table): void {
                $table->string('gateway_payment_id', 255)->nullable()->after('reference');
            });
        }

        // PostgreSQL : les NULL sont distincts → l'index unique accepte les
        // paiements hors-ligne (gateway_payment_id NULL) sans limite.
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS accounting_payments_company_gateway_unique
            ON accounting_payments (company_id, gateway_payment_id)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS accounting_payments_company_gateway_unique');

        if (schemaHasColumn('accounting_payments', 'gateway_payment_id')) {
            Schema::table('accounting_payments', function (Blueprint $table): void {
                $table->dropColumn('gateway_payment_id');
            });
        }
    }
};
