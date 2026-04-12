<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesMvpSchema
{
    protected function setUpMvpSchema(): void
    {
        // Sur PostgreSQL, on utilise CASCADE pour s'assurer que les tables dépendantes ne bloquent pas le nettoyage
        DB::statement('DROP TABLE IF EXISTS "personal_access_tokens" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "user_lookups" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "attendance_logs" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "employees" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "schedules" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "companies" CASCADE');

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('sector');
            $table->char('country', 2);
            $table->string('city');
            $table->string('email');
            $table->unsignedInteger('plan_id')->nullable();
            $table->string('schema_name', 63);
            $table->string('tenancy_type', 20)->default('shared');
            $table->string('status', 20)->default('active');
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->char('language', 2)->default('fr');
            $table->string('timezone', 50)->default('Africa/Algiers');
            $table->char('currency', 3)->default('DZD');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id');
            $table->unsignedInteger('schedule_id')->nullable();
            $table->string('matricule', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150);
            $table->string('password_hash', 255);
            $table->string('contract_type', 20)->default('CDI');
            $table->date('contract_start')->default(DB::raw('CURRENT_DATE'));
            $table->date('contract_end')->nullable();
            $table->string('salary_type', 20)->default('fixed');
            $table->decimal('salary_base', 10, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->string('role', 20)->default('employee');
            $table->string('status', 20)->default('active');
            $table->timestampTz('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
            $table->unique(['company_id', 'matricule']);
        });

        Schema::create('user_lookups', function (Blueprint $table): void {
            $table->string('email', 150)->primary();
            $table->uuid('company_id');
            $table->string('schema_name', 63);
            $table->unsignedInteger('employee_id');
            $table->string('role', 20);
        });

        Schema::create('schedules', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->json('work_days')->nullable();
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->decimal('overtime_threshold_daily', 4, 2)->default(8.00);
            $table->decimal('overtime_threshold_weekly', 5, 2)->default(40.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('schedule_id')->nullable();
            $table->date('date');
            $table->smallInteger('session_number')->default(1);
            $table->timestampTz('check_in')->nullable();
            $table->timestampTz('check_out')->nullable();
            $table->string('method', 20)->default('mobile');
            $table->string('status', 20)->default('incomplete');
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date', 'session_number']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDownMvpSchema(): void
    {
        app()->forgetInstance('current_company');
        DB::statement('DROP TABLE IF EXISTS "personal_access_tokens" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "user_lookups" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "attendance_logs" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "employees" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "schedules" CASCADE');
        DB::statement('DROP TABLE IF EXISTS "companies" CASCADE');
    }
}
