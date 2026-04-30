<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesMvpSchema
{
    protected function setUpMvpSchema(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->preparePostgresSchemas();
            $this->loadPostgresFixtureSchema();
            $this->restoreDefaultSearchPath();

            return;
        }

        $this->preparePostgresSchemas();
        $this->dropMvpTables();

        if (DB::getDriverName() === 'pgsql') {
            $this->setPostgresSearchPath('public');
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
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('features')->default(DB::raw("'{}'::jsonb"));
                $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            } else {
                $table->json('features')->default('{}');
                $table->json('metadata')->default('{}');
            }
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->string('name_fr', 50);
            $table->string('name_native', 50);
            $table->boolean('is_rtl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->setPostgresSearchPath('shared_tenants,public');
        }

        Schema::create($this->tenantTable('schedules'), function (Blueprint $table): void {
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

        Schema::create($this->tenantTable('employees'), function (Blueprint $table): void {
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
            $table->char('preferred_language', 2)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('iban', 255)->nullable();
            $table->string('bank_account', 255)->nullable();
            $table->string('national_id', 255)->nullable();
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
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            } else {
                $table->json('metadata')->default('{}');
            }
            $table->timestamps();

            $table->unique('email');
            $table->unique(['company_id', 'matricule']);
        });

        Schema::create($this->tenantTable('attendance_logs'), function (Blueprint $table): void {
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
            $table->unsignedInteger('corrected_by')->nullable();
            $table->text('correction_note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date', 'session_number']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create($this->tenantTable('biometric_enrollment_requests'), function (Blueprint $table): void {
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

        Schema::create($this->tenantTable('attendance_kiosks'), function (Blueprint $table): void {
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

        Schema::create($this->tenantTable('cameras'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->string('name', 100);
            $table->text('rtsp_url');
            $table->string('location', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('thumbnail_path', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedInteger('created_by');
            $table->string('stream_path_override', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($this->tenantTable('camera_access_tokens'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->string('token', 64)->unique();
            $table->string('label', 150)->nullable();
            $table->string('granted_to_email', 150)->nullable();
            $table->string('granted_to_name', 100)->nullable();
            $table->unsignedInteger('granted_by');
            $table->json('permissions')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->json('ip_whitelist')->nullable();
            $table->timestamps();
        });

        Schema::create($this->tenantTable('camera_permissions'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->unsignedInteger('employee_id');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_share')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->unsignedInteger('granted_by');
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['camera_id', 'employee_id']);
        });

        Schema::create($this->tenantTable('camera_access_logs'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('camera_id');
            $table->unsignedInteger('employee_id')->nullable();
            $table->unsignedInteger('access_token_id')->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 40);
            $table->string('reason', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('absence_types', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->string('name', 100);
            $table->string('code', 30);
            $table->boolean('is_paid')->default(true);
            $table->boolean('deducts_leave')->default(true);
            $table->boolean('requires_proof')->default(false);
            $table->unsignedInteger('max_days_once')->nullable();
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('absences', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('absence_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('proof_path', 255)->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_balance_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->decimal('delta', 8, 2);
            $table->string('reason', 60);
            $table->unsignedInteger('reference_id')->default(0);
            $table->decimal('balance_after', 8, 2);
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('salary_advances', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('approved_by')->nullable();
            $table->text('decision_comment')->nullable();
            $table->unsignedSmallInteger('repayment_months')->default(1);
            $table->decimal('monthly_deduction', 12, 2)->nullable();
            $table->decimal('amount_remaining', 12, 2)->default(0);
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('repayment_plan')->nullable();
            } else {
                $table->json('repayment_plan')->nullable();
            }
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('evaluator_id')->index();
            $table->string('period', 20);
            $table->decimal('score', 4, 2)->nullable();
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('criteria')->default(DB::raw("'[]'::jsonb"));
            } else {
                $table->json('criteria')->default('[]');
            }
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('overall_comment')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period', 'evaluator_id']);
        });

        Schema::create($this->tenantTable('payrolls'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->unsignedSmallInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('projects'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('tasks'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('title', 200);
            $table->unsignedInteger('created_by');
            $table->timestampTz('due_date');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('notifications'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->string('type', 100);
            $table->string('title', 200);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('departments'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create($this->tenantTable('positions'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->unsignedInteger('department_id');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('sites'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->setPostgresSearchPath('public');
        }

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

        $this->restoreDefaultSearchPath();
    }

    protected function tearDownMvpSchema(): void
    {
        app()->forgetInstance('current_company');

        if (DB::getDriverName() === 'pgsql') {
            $this->restoreDefaultSearchPath();

            return;
        }

        $this->dropMvpTables();
        $this->restoreDefaultSearchPath();
    }

    private function preparePostgresSchemas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->setPostgresSearchPath('public');
        $this->dropPostgresPublicTables();

        DB::statement('DROP SCHEMA IF EXISTS shared_tenants CASCADE');
        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
    }

    private function loadPostgresFixtureSchema(): void
    {
        $sql = file_get_contents(__DIR__.'/sql/mvp_schema.pgsql.sql');

        if ($sql === false) {
            throw new \RuntimeException('Unable to load PostgreSQL test schema fixture.');
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');

        foreach ($this->splitPostgresStatements($sql) as $statement) {
            DB::statement($statement);
        }
    }

    private function dropPostgresPublicTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS public.user_invitations CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.super_admins CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.user_lookups CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.personal_access_tokens CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.languages CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.companies CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.plans CASCADE');
    }

    /**
     * Execute fixture SQL statement-by-statement to avoid PostgreSQL rolling
     * back an entire multi-statement batch when one DDL statement fails.
     *
     * @return list<string>
     */
    private function splitPostgresStatements(string $sql): array
    {
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if ($statements === false) {
            throw new \RuntimeException('Unable to split PostgreSQL test schema fixture.');
        }

        return array_values(array_filter(array_map(
            static fn (string $statement): string => trim($statement),
            $statements
        )));
    }

    private function tenantTable(string $table): string
    {
        return $table;
    }

    private function dropMvpTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        $cascade = DB::getDriverName() === 'pgsql' ? ' CASCADE' : '';

        DB::statement('DROP TABLE IF EXISTS "user_invitations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "super_admins"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "personal_access_tokens"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_kiosks"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "biometric_enrollment_requests"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_access_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_permissions"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_access_tokens"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "cameras"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "user_lookups"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "salary_advances"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "evaluations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "leave_balance_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "absences"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "absence_types"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "employees"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "schedules"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "companies"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "languages"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "plans"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "hr_model_templates"'.$cascade);
    }

    private function restoreDefaultSearchPath(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->setPostgresSearchPath('shared_tenants,public');
    }

    private function setPostgresSearchPath(string $path): void
    {
        $connection = config('database.default', 'pgsql');

        config(["database.connections.{$connection}.search_path" => $path]);

        DB::purge($connection);
        DB::reconnect($connection);
        DB::statement("SET search_path TO {$path}");
    }
}
