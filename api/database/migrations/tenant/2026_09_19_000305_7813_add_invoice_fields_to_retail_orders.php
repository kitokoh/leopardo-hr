<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7813 (BC-17 RETAIL) - Numero de facture LEGAL sur les commandes Retail.
 *
 * `invoice_number` (FAC-YYYY-NNNNNN, attribue par RetailInvoiceService a la
 * PREMIERE generation du PDF puis STABLE — jamais reattribue ni recalcule)
 * et `invoiced_at` (horodatage d'emission). Unicite par tenant via un index
 * unique PARTIEL Postgres `(company_id, invoice_number) WHERE invoice_number
 * IS NOT NULL` (les commandes non facturees, invoice_number NULL, coexistent
 * librement — pattern des index partiels #7674/#7717).
 *
 * Additive et idempotente ; down() avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('retail_orders', 'invoice_number')) {
                $table->string('invoice_number', 40)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'invoiced_at')) {
                $table->timestamp('invoiced_at')->nullable();
            }
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS retail_orders_company_invoice_number_unique ON retail_orders (company_id, invoice_number) WHERE invoice_number IS NOT NULL');
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS retail_orders_company_invoice_number_unique');

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('retail_orders', 'invoiced_at')) {
                $table->dropColumn('invoiced_at');
            }

            if (Schema::hasColumn('retail_orders', 'invoice_number')) {
                $table->dropColumn('invoice_number');
            }
        });
    }
};
