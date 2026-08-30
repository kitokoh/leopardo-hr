<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5809 (FUEL-015) — contrat Accounting : agrégats validés publiés.
 *
 * Table des lignes d'écriture générées par le module FuelStation à partir
 * des agrégats validés (ventes du jour, clôtures de caisse, écarts de stock
 * rapprochés). Le module FuelStation reste maître de ses données ; les
 * lignes sont consommées par Accounting (référence traçable
 * `FUEL-SALES-{station}-{day}`, `FUEL-CASH-{session}`, `FUEL-VAR-{...}`).
 *
 * Contrainte d'unicité (company_id, reference) : la régénération est
 * IDEMPOTENTE (remplacement des lignes d'une même référence) — zéro doublon
 * au rejeu du job de synchronisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('station_id')->index();
            $table->date('period');
            // sales|cash_session|stock_variance
            $table->string('entry_type', 20);
            $table->string('account_code', 32);
            $table->string('account_label', 255);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('reference', 160);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Idempotence de la régénération : une ligne par référence.
            $table->unique(['company_id', 'reference'], 'fuel_accounting_entries_reference_unique');
            $table->index(['company_id', 'station_id', 'period'], 'fuel_accounting_entries_company_station_period_idx');

            $table->foreign(['station_id', 'company_id'], 'fuel_accounting_entries_station_company_fk')
                ->references(['id', 'company_id'])
                ->on('fuel_stations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_accounting_entries');
    }
};
