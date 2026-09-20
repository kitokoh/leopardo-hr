<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7799 (PHARMA-002) — référentiel produits d'officine.
 *
 * Identité pharmaceutique (DCI, forme galénique, dosage, code-barres),
 * régulation (ordonnance obligatoire, produit contrôlé → ordonnancier
 * PHARMA-006) et gestion (prix, taxe, seuil d'alerte de stock).
 *
 * Unicité par tenant du code-barres et du code interne (index partiels :
 * NULL autorisé en multiple). Montants en decimal — jamais de float.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pharmacy_products')) {
            Schema::create('pharmacy_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 191);
                $table->string('dci', 191)->nullable();
                $table->string('form', 100)->nullable();
                $table->string('dosage', 100)->nullable();
                $table->string('barcode', 64)->nullable();
                $table->string('internal_code', 64)->nullable();
                $table->string('category', 30)->default('medicament'); // medicament|parapharmacie|dispositif|autre
                $table->string('unit', 30)->default('unite');
                $table->boolean('prescription_required')->default(false);
                $table->boolean('is_controlled')->default(false);
                $table->decimal('purchase_price', 12, 2)->default(0);
                $table->decimal('sale_price', 12, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->unsignedInteger('min_stock_level')->default(0);
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->unique(['company_id', 'barcode'], 'pharmacy_products_company_barcode_unique');
                $table->unique(['company_id', 'internal_code'], 'pharmacy_products_company_internal_code_unique');
                $table->index(['company_id', 'status'], 'pharmacy_products_company_status_idx');
                $table->index(['company_id', 'category'], 'pharmacy_products_company_category_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_products');
    }
};
