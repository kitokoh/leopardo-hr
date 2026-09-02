<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6173 (RESTO-208) - RestaurantManager : sessions POS, commandes, lignes de commande.
 *
 * - `restaurant_pos_sessions` : ouverture/fermeture de caisse (une seule session
 *   `open` par branche via la contrainte unique company+branch+status) ;
 * - `restaurant_orders` : commandes (reference unique par tenant, idempotency_key
 *   unique pour retry sans doublon, totaux en minor units, version pour optimiste) ;
 * - `restaurant_order_items` : lignes de commande (unique tenant+order+product+line_index).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_pos_sessions')) {
            Schema::create('restaurant_pos_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('opened_by_user_id');
                $table->unsignedBigInteger('closed_by_user_id')->nullable();
                $table->unsignedInteger('opening_cash_minor')->default(0);
                $table->unsignedInteger('expected_cash_minor')->nullable();
                $table->unsignedInteger('counted_cash_minor')->nullable();
                $table->integer('variance_minor')->nullable();
                $table->string('variance_reason', 255)->nullable();
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->index(['company_id', 'branch_id'], 'restaurant_pos_sessions_company_branch_idx');
                $table->unique(['company_id', 'branch_id', 'status'], 'restaurant_pos_sessions_company_branch_status_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_pos_sessions IS 'Sessions de caisse POS - unique (tenant, branche, statut) : une seule session ouverte par branche (RESTO-208/#6173).';");
        }

        if (! schemaTableExists('restaurant_orders')) {
            Schema::create('restaurant_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('pos_session_id')->nullable();
                $table->string('reference', 40);
                $table->string('order_type', 20)->default('dine_in');
                $table->unsignedBigInteger('table_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedSmallInteger('covers')->nullable();
                $table->unsignedBigInteger('customer_contact_id')->nullable();
                $table->unsignedBigInteger('rider_id')->nullable();
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('subtotal_minor')->default(0);
                $table->unsignedInteger('tax_minor')->default(0);
                $table->unsignedInteger('discount_minor')->default(0);
                $table->unsignedInteger('total_minor')->default(0);
                $table->char('currency', 3)->default('DZD');
                $table->string('source', 20)->default('pos');
                $table->text('note_redacted')->nullable();
                $table->string('idempotency_key', 64)->nullable();
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'restaurant_orders_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'restaurant_orders_company_idempotency_key_unique');
                $table->index(['company_id', 'branch_id', 'status'], 'restaurant_orders_company_branch_status_idx');
                $table->index(['company_id', 'table_id'], 'restaurant_orders_company_table_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_orders IS 'Commandes - reference et idempotency_key uniques par tenant, totaux en minor units (RESTO-208/#6173).';");
        }

        if (! schemaTableExists('restaurant_order_items')) {
            Schema::create('restaurant_order_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('menu_id')->nullable();
                $table->decimal('quantity', 12, 3);
                $table->unsignedInteger('unit_price_minor');
                $table->unsignedInteger('line_total_minor');
                $table->unsignedBigInteger('tax_rate_id')->nullable();
                $table->unsignedInteger('tax_minor')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedSmallInteger('line_index')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'order_id', 'product_id', 'line_index'], 'restaurant_order_items_company_order_product_line_unique');
                $table->index(['company_id', 'order_id'], 'restaurant_order_items_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_order_items IS 'Lignes de commande - unique par (tenant, commande, produit, ligne), prix en minor units (RESTO-208/#6173).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
        Schema::dropIfExists('restaurant_pos_sessions');
    }
};
