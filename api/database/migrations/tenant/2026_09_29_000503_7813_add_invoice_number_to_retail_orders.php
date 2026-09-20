<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7813 (BC-17 RETAIL) - Recus et factures : numerotation LEGALE des
 * factures par tenant sur `retail_orders`.
 *
 * - `invoice_number` (`FAC-YYYY-NNNNNN`, nullable — assigne a la PREMIERE
 *   generation de la facture PDF, puis IMMUABLE : les rejeux re-rendent le
 *   meme numero, sequence par tenant et par annee sans trou a l'assignation) ;
 * - `invoiced_at` : horodatage d'emission ;
 * - unicite (company_id, invoice_number) en filet de securite (index unique
 *   partiel — la colonne est nullable pour le POS sans facture).
 *
 * Sans FK, idempotente via Schema::hasColumn + index nommes (conventions
 * migrations tenant §2.6). down() complet avec gardes.
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
                $table->string('invoice_number', 30)->nullable();
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
            foreach (['invoiced_at', 'invoice_number'] as $column) {
                if (Schema::hasColumn('retail_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
