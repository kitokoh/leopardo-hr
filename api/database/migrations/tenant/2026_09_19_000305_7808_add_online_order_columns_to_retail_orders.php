<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7808 (BC-17 RETAIL) - Commandes en ligne du marketplace public :
 * colonnes de checkout invite et de cycle de vie sur `retail_orders`.
 *
 * - `customer_*` : coordonnees de l'invite (pas de compte utilisateur) ;
 * - `delivery_*` : adresse de livraison optionnelle (retrait boutique sinon) ;
 * - `fulfillment_status` : machine d'etats des commandes web
 *   pending -> confirmed -> ready -> shipped -> delivered (+ cancelled) —
 *   NULL pour les commandes POS (#7674), qui gardent leur cycle draft/
 *   completed/cancelled ;
 * - `tracking_token` : jeton de suivi public 64 hex remis UNIQUEMENT a
 *   l'invite au checkout (suivi par reference + jeton, 404 fail-closed) ;
 * - `confirmed_at`/`ready_at`/`shipped_at`/`delivered_at`/`cancelled_at` :
 *   horodatage de chaque transition (piste d'audit) ;
 * - `retail_online_settings.location_id` : emplacement de stock qui sert
 *   les commandes en ligne (decrement au confirm via RetailStockService).
 *
 * Additive, idempotente, sans FK (conventions migrations tenant §2.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('retail_orders')) {
            Schema::table('retail_orders', function (Blueprint $table): void {
                if (! schemaHasColumn('retail_orders', 'customer_name')) {
                    $table->string('customer_name', 160)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'customer_phone')) {
                    $table->string('customer_phone', 40)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'customer_email')) {
                    $table->string('customer_email', 160)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'delivery_address')) {
                    $table->string('delivery_address', 255)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'delivery_city')) {
                    $table->string('delivery_city', 120)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'delivery_note')) {
                    $table->string('delivery_note', 500)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'fulfillment_status')) {
                    $table->string('fulfillment_status', 20)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'tracking_token')) {
                    $table->char('tracking_token', 64)->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'ready_at')) {
                    $table->timestamp('ready_at')->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'shipped_at')) {
                    $table->timestamp('shipped_at')->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable();
                }

                if (! schemaHasColumn('retail_orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
            });

            // Suivi public par (reference, jeton) : la reference n'est unique
            // que PAR tenant — l'index global sur le jeton borne la recherche
            // cross-tenant fail-closed du contrôleur public.
            DB::statement('CREATE INDEX IF NOT EXISTS retail_orders_tracking_token_idx ON retail_orders (tracking_token)');
            DB::statement('CREATE INDEX IF NOT EXISTS retail_orders_company_fulfillment_idx ON retail_orders (company_id, fulfillment_status)');
        }

        if (schemaTableExists('retail_online_settings') && ! schemaHasColumn('retail_online_settings', 'location_id')) {
            Schema::table('retail_online_settings', function (Blueprint $table): void {
                $table->unsignedBigInteger('location_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_online_settings') && schemaHasColumn('retail_online_settings', 'location_id')) {
            Schema::table('retail_online_settings', function (Blueprint $table): void {
                $table->dropColumn('location_id');
            });
        }

        if (schemaTableExists('retail_orders')) {
            DB::statement('DROP INDEX IF EXISTS retail_orders_company_fulfillment_idx');
            DB::statement('DROP INDEX IF EXISTS retail_orders_tracking_token_idx');

            Schema::table('retail_orders', function (Blueprint $table): void {
                foreach ([
                    'cancelled_at', 'delivered_at', 'shipped_at', 'ready_at', 'confirmed_at',
                    'tracking_token', 'fulfillment_status',
                    'delivery_note', 'delivery_city', 'delivery_address',
                    'customer_email', 'customer_phone', 'customer_name',
                ] as $column) {
                    if (schemaHasColumn('retail_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
