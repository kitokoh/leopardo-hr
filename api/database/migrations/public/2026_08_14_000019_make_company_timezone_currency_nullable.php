<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 000019 - Schema public : companies.timezone / companies.currency
 *
 * Les tenants « legacy » (antérieurs au verrou pays #1952) peuvent avoir
 * country='' avec timezone/currency NULL tant que l'endpoint de réparation
 * admin (PATCH /platform/companies/{id}/country, #1873) ne les a pas
 * backfillés. Les colonnes portent un DEFAULT pour les nouvelles lignes
 * (Africa/Algiers / DZD) mais doivent accepter NULL — aligne le schéma sur
 * le test TenantCountryLocksTest::test_admin_can_repair_country_on_legacy_tenant_without_payroll.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');
        Schema::table('companies', function (Blueprint $table) {
            $table->string('timezone', 50)->nullable()->change();
            $table->char('currency', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');
        Schema::table('companies', function (Blueprint $table) {
            $table->string('timezone', 50)->default('Africa/Algiers')->change();
            $table->char('currency', 3)->default('DZD')->change();
        });
    }
};
