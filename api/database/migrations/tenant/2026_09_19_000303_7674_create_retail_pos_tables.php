<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7674 (BC-17 RETAIL) - POS v1 du module vendeur : sessions de caisse,
 * commandes de vente, lignes, paiements multi-moyens.
 *
 * - `retail_pos_sessions` : ouverture/fermeture de caisse par emplacement.
 *   Une seule session `open` par emplacement via un INDEX UNIQUE PARTIEL
 *   Postgres `(company_id, location_id) WHERE status = 'open'` — deviation
 *   assumee du pattern restaurant_pos_sessions (#6173) dont l'unique
 *   (company, branche, statut) empeche deux sessions FERMEES de coexister
 *   sur la meme branche (defaut constate lors de l'audit #7674) ;
 * - `retail_orders` : commandes (reference et idempotency_key uniques par
 *   tenant, totaux en minor units, version pour verrou optimiste) ;
 * - `retail_order_items` : lignes de commande avec snapshot du nom produit
 *   (unique tenant+commande+produit+ligne) ;
 * - `retail_order_payments` : paiements multi-moyens (cash|card|mobile|online
 *   — `online` reserve a la future boutique e-commerce, aucune passerelle),
 *   idempotency_key unique par tenant pour le rejeu sans doublon.
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern RestaurantManager #6173/#6174).
 * Idempotente + down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_pos_sessions')) {
            Schema::create('retail_pos_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('location_id');
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('opened_by_user_id');
                $table->unsignedBigInteger('closed_by_user_id')->nullable();
                $table->unsignedBigInteger('opening_cash_minor')->default(0);
                $table->unsignedBigInteger('expected_cash_minor')->nullable();
                $table->unsignedBigInteger('counted_cash_minor')->nullable();
                $table->bigInteger('variance_minor')->nullable();
                $table->string('variance_reason', 255)->nullable();
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->index(['company_id', 'location_id'], 'retail_pos_sessions_company_location_idx');
            });

            // Une seule session OUVERTE par (tenant, emplacement) : index
            // unique partiel Postgres (le repo est Postgres-only). Deviation
            // du pattern restaurant (#6173, unique company+branch+status) qui
            // interdirait deux sessions fermees sur le meme emplacement.
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS retail_pos_sessions_company_location_open_unique ON retail_pos_sessions (company_id, location_id) WHERE status = 'open'");

            DB::statement("COMMENT ON TABLE retail_pos_sessions IS 'Sessions de caisse POS Retail - une seule session open par (tenant, emplacement) via index unique partiel (BC-17/#7674).';");
        }

        if (! schemaTableExists('retail_orders')) {
            Schema::create('retail_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('pos_session_id')->nullable();
                $table->string('reference', 40);
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('subtotal_minor')->default(0);
                $table->unsignedBigInteger('discount_minor')->default(0);
                $table->unsignedBigInteger('total_minor')->default(0);
                $table->char('currency', 3)->default('XOF');
                $table->string('source', 20)->default('pos');
                $table->text('note')->nullable();
                $table->string('idempotency_key', 64)->nullable();
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'retail_orders_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'retail_orders_company_idempotency_key_unique');
                $table->index(['company_id', 'location_id', 'status'], 'retail_orders_company_location_status_idx');
            });

            DB::statement("COMMENT ON TABLE retail_orders IS 'Commandes de vente Retail - reference et idempotency_key uniques par tenant, totaux en minor units, statut draft|completed|cancelled (BC-17/#7674).';");
        }

        if (! schemaTableExists('retail_order_items')) {
            Schema::create('retail_order_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id');
                $table->string('product_name', 200);
                $table->decimal('quantity', 12, 3);
                $table->unsignedBigInteger('unit_price_minor');
                $table->unsignedBigInteger('line_total_minor');
                $table->unsignedSmallInteger('line_index')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'order_id', 'product_id', 'line_index'], 'retail_order_items_company_order_product_line_unique');
                $table->index(['company_id', 'order_id'], 'retail_order_items_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE retail_order_items IS 'Lignes de commande Retail - snapshot du nom produit, prix en minor units, unique (tenant, commande, produit, ligne) (BC-17/#7674).';");
        }

        if (! schemaTableExists('retail_order_payments')) {
            Schema::create('retail_order_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('pos_session_id')->nullable();
                $table->string('method', 20);
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3)->default('XOF');
                $table->string('status', 20)->default('captured');
                $table->timestamp('paid_at')->nullable();
                $table->string('reference', 120)->nullable();
                $table->string('idempotency_key', 64)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'retail_order_payments_company_idempotency_key_unique');
                $table->index(['company_id', 'order_id'], 'retail_order_payments_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE retail_order_payments IS 'Paiements de commande Retail - methode cash|card|mobile|online (online reserve e-commerce), idempotency_key unique par tenant (BC-17/#7674).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_order_payments')) {
            Schema::dropIfExists('retail_order_payments');
        }

        if (schemaTableExists('retail_order_items')) {
            Schema::dropIfExists('retail_order_items');
        }

        if (schemaTableExists('retail_orders')) {
            Schema::dropIfExists('retail_orders');
        }

        if (schemaTableExists('retail_pos_sessions')) {
            Schema::dropIfExists('retail_pos_sessions');
        }
    }
};
