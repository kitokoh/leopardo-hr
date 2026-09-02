<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6171 (RESTO-206) - RestaurantManager : bons de commande, lignes, receptions.
 *
 * - `restaurant_purchase_orders` : commandes fournisseur (reference unique par tenant,
 *   statut draft/ordered/received/cancelled) ;
 * - `restaurant_purchase_order_items` : lignes de commande (quantite, prix unitaire) ;
 * - `restaurant_receivings` : receptions de marchandises (reference unique par tenant).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_purchase_orders')) {
            Schema::create('restaurant_purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('supplier_id');
                $table->string('reference', 40);
                $table->string('status', 20)->default('draft');
                $table->timestamp('expected_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->unsignedInteger('total_minor')->nullable();
                $table->char('currency', 3)->default('DZD');

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'restaurant_purchase_orders_company_reference_unique');
                $table->index(['company_id', 'supplier_id'], 'restaurant_purchase_orders_company_supplier_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_purchase_orders IS 'Bons de commande fournisseur - reference unique par tenant, montants en minor units (RESTO-206/#6171).';");
        }

        if (! schemaTableExists('restaurant_purchase_order_items')) {
            Schema::create('restaurant_purchase_order_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 12, 3);
                $table->unsignedInteger('unit_price_minor');
                $table->unsignedInteger('line_total_minor');

                $table->timestamps();

                $table->index(['company_id', 'purchase_order_id'], 'restaurant_purchase_order_items_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_purchase_order_items IS 'Lignes de bon de commande - quantites decimales, prix en minor units (RESTO-206/#6171).';");
        }

        if (! schemaTableExists('restaurant_receivings')) {
            Schema::create('restaurant_receivings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('reference', 40);
                $table->timestamp('received_at')->useCurrent();
                $table->text('note_redacted')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'restaurant_receivings_company_reference_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_receivings IS 'Receptions de marchandises - reference unique par tenant (RESTO-206/#6171).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_receivings');
        Schema::dropIfExists('restaurant_purchase_order_items');
        Schema::dropIfExists('restaurant_purchase_orders');
    }
};
