<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
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
        DB::statement("SET search_path TO public");

        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        if (Schema::hasTable('plans')) {
            return;
        }

        try {
            Schema::create('plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 50)->unique();
                $table->decimal('price_monthly', 10, 2)->default(0);
                $table->decimal('price_yearly', 10, 2)->default(0);
                $table->unsignedInteger('max_employees')->nullable();
                $table->jsonb('features')->default('{}');
                $table->unsignedSmallInteger('trial_days')->default(14);
                $table->boolean('is_active')->default(true);
                $table->timestampTz('created_at')->useCurrent();
                $table->comment('Plans tarifaires SaaS — Starter/Business/Enterprise');
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42P07') {
                throw $exception;
            }
        }

        if (Schema::hasTable('plans')) {
            DB::statement("COMMENT ON COLUMN plans.max_employees IS 'NULL = employés illimités (Enterprise)'");
            DB::statement("COMMENT ON COLUMN plans.features IS 'JSONB: {biometric, tasks, advanced_reports, excel_export, bank_export, billing_auto, multi_managers, photo_attendance, api_public, evaluations, schema_isolation}'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
