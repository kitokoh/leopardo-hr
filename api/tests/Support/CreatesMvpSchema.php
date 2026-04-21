<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesMvpSchema
{
    protected function setUpMvpSchema(): void
    {
        $this->preparePostgresSchemas();
        $this->dropMvpTables();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::create('plans', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 50);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->unsignedInteger('max_employees')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('sector');
            $table->char('country', 2);
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->unsignedInteger('plan_id')->nullable();
            $table->string('schema_name', 63);
            $table->string('tenancy_type', 20)->default('shared');
            $table->string('status', 20)->default('active');
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->char('language', 2)->default('fr');
            $table->string('timezone', 50)->default('Africa/Algiers');
            $table->char('currency', 3)->default('DZD');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }

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

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id');
            $table->unsignedInteger('schedule_id')->nullable();
            $table->string('matricule', 20)->nullable();
            $table->string('zkteco_id', 50)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('preferred_name', 100)->nullable();
            $table->string('email', 150);
            $table->string('personal_email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('password_hash', 255);
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth', 120)->nullable();
            $table->char('gender', 1)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('contract_type', 20)->default('CDI');
            $table->date('contract_start')->default(DB::raw('CURRENT_DATE'));
            $table->date('contract_end')->nullable();
            $table->string('salary_type', 20)->default('fixed');
            $table->decimal('salary_base', 10, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->string('role', 20)->default('employee');
            $table->string('manager_role', 30)->nullable();
            $table->unsignedInteger('manager_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('photo_path', 255)->nullable();
            $table->boolean('biometric_face_enabled')->default(false);
            $table->boolean('biometric_fingerprint_enabled')->default(false);
            $table->string('biometric_face_reference_path', 255)->nullable();
            $table->string('biometric_fingerprint_reference_path', 255)->nullable();
            $table->timestampTz('biometric_consent_at')->nullable();
            $table->timestampTz('invitation_accepted_at')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relation', 60)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->unique('email');
            $table->unique(['company_id', 'matricule']);
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
            $table->string('source_device_code', 40)->nullable();
            $table->string('external_event_id', 100)->nullable()->unique();
            $table->string('biometric_type', 20)->nullable();
            $table->boolean('synced_from_offline')->default(false);
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

        Schema::create('biometric_enrollment_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('approver_employee_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->boolean('requested_face_enabled')->default(false);
            $table->boolean('requested_fingerprint_enabled')->default(false);
            $table->string('requested_face_reference_path', 255)->nullable();
            $table->string('requested_fingerprint_reference_path', 255)->nullable();
            $table->string('requested_fingerprint_device_id', 100)->nullable();
            $table->string('request_source', 30)->default('mobile');
            $table->text('employee_note')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_kiosks', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->string('name', 100);
            $table->string('location_label', 120)->nullable();
            $table->string('device_code', 40)->unique();
            $table->string('sync_token_hash', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('biometric_mode', 30)->default('fingerprint');
            $table->string('trusted_device_label', 120)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestamps();
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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE TABLE public.user_lookups (
                email varchar(150) primary key,
                company_id uuid not null,
                schema_name varchar(63) not null,
                employee_id integer not null,
                role varchar(20) not null
            )');
            DB::statement('CREATE TABLE public.super_admins (
                id serial primary key,
                name varchar(100) not null,
                email varchar(150) not null unique,
                password_hash varchar(255) not null,
                two_fa_secret varchar(32) null,
                last_login_at timestamp with time zone null,
                created_at timestamp with time zone null
            )');
            DB::statement('CREATE TABLE public.user_invitations (
                id uuid primary key,
                company_id uuid not null,
                schema_name varchar(63) not null,
                employee_id integer not null,
                email varchar(150) not null,
                role varchar(20) not null,
                manager_role varchar(30) null,
                invited_by_type varchar(20) not null,
                invited_by_email varchar(150) not null,
                token_hash varchar(64) not null unique,
                expires_at timestamp with time zone not null,
                accepted_at timestamp with time zone null,
                last_sent_at timestamp with time zone null,
                metadata jsonb null,
                created_at timestamp with time zone null,
                updated_at timestamp with time zone null
            )');
        } else {
            Schema::create('user_lookups', function (Blueprint $table): void {
                $table->string('email', 150)->primary();
                $table->uuid('company_id');
                $table->string('schema_name', 63);
                $table->unsignedInteger('employee_id');
                $table->string('role', 20);
            });
            Schema::create('super_admins', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('email', 150)->unique();
                $table->string('password_hash', 255);
                $table->string('two_fa_secret', 32)->nullable();
                $table->timestampTz('last_login_at')->nullable();
                $table->timestampTz('created_at')->nullable();
            });
            Schema::create('user_invitations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id');
                $table->string('schema_name', 63);
                $table->unsignedInteger('employee_id');
                $table->string('email', 150);
                $table->string('role', 20);
                $table->string('manager_role', 30)->nullable();
                $table->string('invited_by_type', 20);
                $table->string('invited_by_email', 150);
                $table->string('token_hash', 64)->unique();
                $table->timestampTz('expires_at');
                $table->timestampTz('accepted_at')->nullable();
                $table->timestampTz('last_sent_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $this->restoreDefaultSearchPath();
    }

    protected function tearDownMvpSchema(): void
    {
        app()->forgetInstance('current_company');
        $this->dropMvpTables();
        $this->restoreDefaultSearchPath();
    }

    private function preparePostgresSchemas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS public');
        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
    }

    private function dropMvpTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS public.user_lookups CASCADE');
            DB::statement('DROP TABLE IF EXISTS public.user_invitations CASCADE');
            DB::statement('DROP TABLE IF EXISTS public.super_admins CASCADE');
            DB::statement('DROP TABLE IF EXISTS public.hr_model_templates CASCADE');
            DB::statement('DROP TABLE IF EXISTS public.companies CASCADE');
            DB::statement('DROP TABLE IF EXISTS public.plans CASCADE');
            DB::statement('DROP TABLE IF EXISTS shared_tenants.personal_access_tokens CASCADE');
            DB::statement('DROP TABLE IF EXISTS shared_tenants.attendance_logs CASCADE');
            DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
            DB::statement('DROP TABLE IF EXISTS shared_tenants.schedules CASCADE');
        }

        $cascade = DB::getDriverName() === 'pgsql' ? ' CASCADE' : '';

        DB::statement('DROP TABLE IF EXISTS "user_invitations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "super_admins"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "personal_access_tokens"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_kiosks"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "biometric_enrollment_requests"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "user_lookups"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "employees"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "schedules"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "companies"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "plans"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "hr_model_templates"'.$cascade);
    }

    private function restoreDefaultSearchPath(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
    }
}
