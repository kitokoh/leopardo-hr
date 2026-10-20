<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-38 PAIEMENTS (#8692) — historique des rapprochements.
 *
 * Chaque ligne compare le solde EXTERNE declare par l'instance au solde
 * INTERNE recalcule depuis le ledger, pour une instance et une devise donnees.
 * `difference = declared - computed` : c'est la seule metrique qui compte, et
 * elle est conservee dans l'historique (un ecart corrige reste une trace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('provider_instance_id');
            $table->char('currency', 3);

            $table->bigInteger('declared_balance');
            $table->bigInteger('computed_balance');
            $table->bigInteger('difference');

            // ok | discrepancy
            $table->string('status', 20);

            $table->timestampTz('checked_at');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'provider_instance_id', 'currency']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
