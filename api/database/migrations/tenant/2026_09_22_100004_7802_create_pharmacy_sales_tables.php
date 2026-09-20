<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7802 (PHARMA-005) — ventes comptoir (POS) d'officine.
 *
 * `pharmacy_sales` : numéro `VT-YYYY-XXXXX` séquencé PAR TENANT, méthode de
 * paiement bornée, statut completed|voided (une vente annulée est CONSERVÉE,
 * jamais supprimée — le stock est ré-crédité par mouvements `return`).
 * `pharmacy_sale_lines` : prix unitaire et taux de taxe FIGÉS à la vente,
 * totaux calculés serveur (decimal — jamais de float).
 *
 * La répartition par lots d'une vente vit dans le journal immuable
 * `pharmacy_stock_movements` (reference_type=pharmacy_sale) : c'est la
 * source de vérité du void (contre-passation) et de l'ordonnancier.
 *
 * Gardes F-17 : schemaTableExists(), migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pharmacy_sales')) {
            Schema::create('pharmacy_sales', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('number', 20);
                $table->timestamp('sold_at');
                $table->string('customer_name', 191)->nullable();
                $table->unsignedBigInteger('prescription_id')->nullable();
                $table->string('payment_method', 20)->default('cash'); // cash|card|mobile|insurance
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('completed'); // completed|voided
                $table->string('void_reason', 255)->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->unsignedBigInteger('sold_by_employee_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'number'], 'pharmacy_sales_company_number_unique');
                $table->index(['company_id', 'status', 'sold_at'], 'pharmacy_sales_company_status_sold_idx');
                $table->index(['company_id', 'payment_method'], 'pharmacy_sales_company_payment_idx');
                $table->index(['company_id', 'prescription_id'], 'pharmacy_sales_company_prescription_idx');
            });

            $schema = resolveTableSchema('pharmacy_sales');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_sales_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_sales\" ADD CONSTRAINT pharmacy_sales_status_check "
                    ."CHECK (status IN ('completed','voided')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_sales_payment_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_sales\" ADD CONSTRAINT pharmacy_sales_payment_check "
                    ."CHECK (payment_method IN ('cash','card','mobile','insurance')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('pharmacy_sale_lines')) {
            Schema::create('pharmacy_sale_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('sale_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 12, 2); // figé à la vente
                $table->decimal('tax_rate', 5, 2)->default(0); // figé à la vente
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->index(['company_id', 'sale_id'], 'pharmacy_sale_lines_company_sale_idx');
                $table->index(['company_id', 'product_id'], 'pharmacy_sale_lines_company_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_sale_lines');
        Schema::dropIfExists('pharmacy_sales');
    }
};
