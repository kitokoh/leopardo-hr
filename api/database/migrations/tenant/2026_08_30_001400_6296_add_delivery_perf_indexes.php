<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6296 (BC-26-D10) — Index de performance complémentaire du module Delivery.
 *
 * `delivery_stops (route_id, sort_order)` : lecture ordonnée des stops d'une
 * tournée (app mobile livreur, détail tournée dispatcher) — évite un tri
 * coûteux sur les tournées volumineuses. Les index
 * `delivery_deliveries_company_status_date_idx` et
 * `delivery_events_company_delivery_idx` (déjà créés par DELIVERY-102/#6283)
 * sont déclarés au registre MAT-014 (performance-budgets.json).
 *
 * Tenant-first, sans FK, réentrante + down() complet (MIGRATIONS_CONVENTIONS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('delivery_stops')) {
            return;
        }

        Schema::table('delivery_stops', function (Blueprint $table): void {
            $table->index(['route_id', 'sort_order'], 'delivery_stops_route_sort_idx');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('delivery_stops')) {
            return;
        }

        Schema::table('delivery_stops', function (Blueprint $table): void {
            $table->dropIndex('delivery_stops_route_sort_idx');
        });
    }
};
