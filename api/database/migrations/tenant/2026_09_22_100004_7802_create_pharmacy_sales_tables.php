<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7802 (PHARMA-005) — ventes comptoir (POS) de l'officine.
 *
 * - `pharmacy_sales` : vente comptoir, numéro `VT-YYYY-XXXXX` séquencé PAR
 *   TENANT (unique (company_id, number)), méthode de paiement contrôlée
 *   (cash|card|mobile|insurance), totaux calculés SERVEUR (decimal),
 *   `prescription_id` nullable (référence de l'ordonnance — la table des
 *   ordonnances arrive avec PHARMA-006/#7803), statut completed|voided :
 *   une vente annulée est CONSERVÉE (raison, auteur, date), jamais
 *   supprimée.
 * - `pharmacy_sale_lines` : lignes (produit, quantité, prix unitaire et
 *   taux de taxe FIGÉS à la vente, total ligne).
 *
 * La délivrance décrémente le stock en FEFO via PharmacyStockService
 * (#7800) — mouvements `sale`, annulation → mouvements `return` sur les
 * lots d'origine. Tenant-scoped, sans FK (conventions migrations tenant
 * §2.6). Idempotente + down() complet avec gardes.
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
                $table->string('payment_method', 20); // cash|card|mobile|insurance
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('completed'); // completed|voided
                $table->unsignedBigInteger('sold_by_employee_id')->nullable();
                $table->string('void_reason', 500)->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->unsignedBigInteger('voided_by_employee_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'pharmacy_sales_company_number_unique');
                $table->index(['company_id', 'status'], 'pharmacy_sales_company_status_idx');
                $table->index(['company_id', 'sold_at'], 'pharmacy_sales_company_sold_at_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_sales IS 'Ventes comptoir d''officine - numero VT-YYYY-XXXXX sequence par tenant, totaux serveur, void conserve (PHARMA-005/#7802).';");
        }

        if (! schemaTableExists('pharmacy_sale_lines')) {
            Schema::create('pharmacy_sale_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('sale_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('line_total', 12, 2);

                $table->timestamps();

                $table->index(['company_id', 'sale_id'], 'pharmacy_sale_lines_company_sale_idx');
                $table->index(['company_id', 'product_id'], 'pharmacy_sale_lines_company_product_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_sale_lines IS 'Lignes de vente comptoir d''officine - prix unitaire et taux de taxe FIGES a la vente (PHARMA-005/#7802).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('pharmacy_sale_lines')) {
            Schema::dropIfExists('pharmacy_sale_lines');
        }

        if (schemaTableExists('pharmacy_sales')) {
            Schema::dropIfExists('pharmacy_sales');
        }
    }
};
