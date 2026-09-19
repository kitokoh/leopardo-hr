<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7808 (BC-17 RETAIL, epic Leopardo Marche) - Commandes en ligne invite :
 * champs du canal `online` sur `retail_orders` (nullables pour le POS).
 *
 * - Coordonnees client invite : `customer_name`, `customer_phone`,
 *   `customer_email` (email optionnel — RGPD, spec §7) ;
 * - Livraison a domicile : `delivery_address`, `delivery_city`,
 *   `delivery_notes` ;
 * - Machine d'etats logistique `fulfillment_status`
 *   (pending|confirmed|ready|shipped|delivered|cancelled), indexee par
 *   tenant — le statut historique `status` reste la source du stock ;
 * - Suivi public : `tracking_token` (64 hex), UNIQUE par
 *   (company_id, tracking_token) — jeton obligatoire, 404 fail-closed ;
 * - Horodatages auditables : `confirmed_at`, `shipped_at`, `delivered_at`.
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
            if (! Schema::hasColumn('retail_orders', 'customer_name')) {
                $table->string('customer_name', 160)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'customer_phone')) {
                $table->string('customer_phone', 40)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'customer_email')) {
                $table->string('customer_email', 160)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'delivery_address')) {
                $table->string('delivery_address', 255)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'delivery_city')) {
                $table->string('delivery_city', 120)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 20)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'tracking_token')) {
                $table->string('tracking_token', 64)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
        });

        // Index nommes, crees hors Blueprint pour rester idempotents.
        DB::statement('CREATE INDEX IF NOT EXISTS retail_orders_company_fulfillment_status_idx ON retail_orders (company_id, fulfillment_status)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS retail_orders_company_tracking_token_unique ON retail_orders (company_id, tracking_token)');
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS retail_orders_company_tracking_token_unique');
        DB::statement('DROP INDEX IF EXISTS retail_orders_company_fulfillment_status_idx');

        Schema::table('retail_orders', function (Blueprint $table): void {
            foreach ([
                'delivered_at',
                'shipped_at',
                'confirmed_at',
                'tracking_token',
                'fulfillment_status',
                'delivery_notes',
                'delivery_city',
                'delivery_address',
                'customer_email',
                'customer_phone',
                'customer_name',
            ] as $column) {
                if (Schema::hasColumn('retail_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
