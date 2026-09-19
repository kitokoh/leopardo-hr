<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7813 (BC-17 RETAIL) - Sequence de numerotation LEGALE des factures de
 * vente Retail : un compteur PAR (tenant, annee civile), incremente sous
 * verrou transactionnel (`lockForUpdate`, RetailInvoiceService) — la
 * numerotation est continue, sans trou ni doublon, et repart a 1 chaque
 * annee (format `FAC-YYYY-NNNNNN`).
 *
 * Unique `(company_id, year)` : filet de securite en cas de course sur la
 * creation initiale du compteur (le service recupere via savepoint,
 * pattern #6954).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6). Idempotente + down() avec garde.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_invoice_sequences')) {
            Schema::create('retail_invoice_sequences', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedSmallInteger('year');
                $table->unsignedBigInteger('next_number')->default(1);

                $table->timestamps();

                $table->unique(['company_id', 'year'], 'retail_invoice_sequences_company_year_unique');
            });

            DB::statement("COMMENT ON TABLE retail_invoice_sequences IS 'Sequences de numerotation legale des factures Retail - un compteur par (tenant, annee), incremente sous verrou transactionnel (BC-17/#7813).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_invoice_sequences');
    }
};
