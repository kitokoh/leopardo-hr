<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6069 (TRAVEL-417) — Synthèse des ventes pour Accounting.
 *
 * Agrégat idempotent par (tenant, période, devise) : même période →
 * mêmes montants (rejouable). L'événement `travel.sales.settled.v1` est
 * publié après commit ; Accounting construit ses écritures depuis ce
 * contrat validé — la verticale n'écrit JAMAIS dans les tables Accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_sales_settlements')) {
            Schema::create('travel_sales_settlements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->char('currency', 3);
                $table->unsignedInteger('confirmed_payments_count')->default(0);
                $table->unsignedBigInteger('confirmed_amount_minor')->default(0);
                $table->unsignedInteger('refunded_count')->default(0);
                $table->unsignedBigInteger('refunded_amount_minor')->default(0);
                $table->bigInteger('net_amount_minor')->default(0);
                $table->string('status', 20)->default('settled');
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'period_start', 'period_end', 'currency'],
                    'travel_sales_settlements_company_period_currency_unique',
                );
                $table->index(['company_id', 'period_start'], 'travel_sales_settlements_company_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_sales_settlements');
    }
};
