<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 0001 — Table publique : plans
 *
 * Schéma : public
 * Dépendances : aucune — première migration à exécuter
 */
return new class extends Migration
{
    public function up(): void
    {
        // S'assurer qu'on est sur le schéma public
        DB::statement("SET search_path TO public");

        // Extensions PostgreSQL (idempotent)
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->unique();                    // 'Starter' | 'Business' | 'Enterprise'
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->unsignedInteger('max_employees')->nullable();    // NULL = illimité (Enterprise)
            $table->jsonb('features')->default('{}');               // {"biometric":bool, "tasks":bool, ...}
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->comment('Plans tarifaires SaaS — Starter/Business/Enterprise');
        });

        DB::statement("COMMENT ON COLUMN plans.max_employees IS 'NULL = employés illimités (Enterprise)'");
        DB::statement("COMMENT ON COLUMN plans.features IS 'JSONB: {biometric, tasks, advanced_reports, excel_export, bank_export, billing_auto, multi_managers, photo_attendance, api_public, evaluations, schema_isolation}'");
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
