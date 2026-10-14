<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8477 (TRADE-002) — BC-36 TRADE : table tenant `trade_products`.
 *
 * Catalogue B2B de l'entreprise : produit, description, categorie, code
 * douanier HS (nullable — format 6 a 10 chiffres, JAMAIS invente), pays
 * d'origine, unite, MOQ, prix indicatif, specifications libres.
 *
 * `status` : `active|archived` (archivage, jamais de suppression dure — les
 * RFQ et offres passees referencent le produit).
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.1
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_products')) {
            Schema::create('trade_products', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('category', 120)->nullable();

                // Code douanier harmonise : nullable — saisi par l'utilisateur
                // ou absent. Le systeme ne le devine jamais.
                $table->string('hs_code', 12)->nullable();
                $table->char('origin_country', 2)->nullable();

                $table->string('unit', 20)->default('unit');
                $table->decimal('moq_quantity', 14, 3)->nullable();

                $table->bigInteger('indicative_unit_price_minor')->nullable();
                $table->char('currency', 3)->nullable();

                $table->json('specs')->nullable();

                $table->string('status', 20)->default('active');

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status'], 'trade_products_company_status_idx');
                $table->index(['company_id', 'category'], 'trade_products_company_category_idx');
            });

            DB::statement("COMMENT ON TABLE trade_products IS 'Catalogue produits B2B Trade - HS code nullable jamais invente, MOQ, prix indicatif en minor units, archivage logique (TRADE-002/#8477).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_products');
    }
};
