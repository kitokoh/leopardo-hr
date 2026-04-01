<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0001 — departments + positions + schedules + sites
 *
 * IMPORTANT : Ces migrations s'exécutent sur le schéma tenant actif.
 * En mode shared  → search_path = shared_tenants
 * En mode schema  → search_path = company_{uuid}
 *
 * Ordre de migration obligatoire (dépendance circulaire departments ↔ employees) :
 * T-01 : departments (SANS manager_id)
 * T-02 : positions, schedules, sites
 * T-03 : employees
 * T-04 : ALTER TABLE departments ADD COLUMN manager_id (FK → employees)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── departments ────────────────────────────────────────────────────────
        // manager_id est ajouté APRÈS employees (migration T-04) pour résoudre
        // la dépendance circulaire departments ↔ employees
        Schema::create('departments', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();        // NULL en mode schema isolé
            $table->string('name', 100);
            // manager_id ajouté en migration T-04 (après employees)
            $table->timestampTz('created_at')->useCurrent();

            $table->comment('Départements de l\'entreprise. manager_id ajouté après la table employees (dépendance circulaire)');
        });

        DB::statement("COMMENT ON COLUMN departments.company_id IS 'NULL en mode schema (isolation physique). Présent uniquement en mode shared (isolation logique)'");

        // ── positions ──────────────────────────────────────────────────────────
        Schema::create('positions', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 100);
            $table->unsignedInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── schedules ──────────────────────────────────────────────────────────
        Schema::create('schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 100);
            $table->time('start_time');                             // ex: '08:00:00'
            $table->time('end_time');                               // ex: '17:00:00'
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->jsonb('work_days')->default('[1,2,3,4,5]');    // 1=Lundi ... 7=Dimanche
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->decimal('overtime_threshold_daily', 4, 2)->default(8.00);   // heures/jour avant HS
            $table->decimal('overtime_threshold_weekly', 5, 2)->default(40.00); // heures/semaine avant HS
            $table->boolean('is_default')->default(false);
            $table->timestampTz('created_at')->useCurrent();

            $table->comment('Plannings de travail. work_days: tableau JSON [1-7], 1=Lundi');
        });

        // ── sites ──────────────────────────────────────────────────────────────
        Schema::create('sites', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 100);
            $table->text('address')->nullable();
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->unsignedSmallInteger('gps_radius_m')->default(100);         // rayon de tolérance GPS
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
