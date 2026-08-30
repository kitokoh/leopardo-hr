<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5810 (FUEL-016) — FuelStation : liaison CRM client (par valeur).
 *
 * Ajoute `customer_contact_id` (référence tenant-scopée vers `crm_contacts`,
 * sans FK — référence par valeur, pattern verticales) et `marketing_consent`
 * (opt-in RGPD explicite capturé à la vente) à `fuel_sales`.
 * Additive et réentrante (garde `hasColumn`, règle #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_sales')) {
            return;
        }

        Schema::table('fuel_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('fuel_sales', 'customer_contact_id')) {
                $table->unsignedBigInteger('customer_contact_id')->nullable()->after('cash_session_id');
                $table->index(['company_id', 'customer_contact_id'], 'fuel_sales_company_contact_idx');
            }

            if (! Schema::hasColumn('fuel_sales', 'marketing_consent')) {
                $table->boolean('marketing_consent')->default(false)->after('customer_contact_id');
            }
        });

        DB::statement("COMMENT ON COLUMN fuel_sales.customer_contact_id IS 'Reference par valeur vers crm_contacts (tenant) - FUEL-016/#5810.';");
        DB::statement("COMMENT ON COLUMN fuel_sales.marketing_consent IS 'Opt-in RGPD marketing capture a la vente - FUEL-016/#5810.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('fuel_sales')) {
            return;
        }

        Schema::table('fuel_sales', function (Blueprint $table): void {
            $table->dropColumn(['customer_contact_id', 'marketing_consent']);
        });
    }
};
