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
            $this->createPostSprintModuleTables();
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
            $table->unsignedBigInteger('referrer_partner_id')->nullable();
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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password_hash')->nullable();
            $table->string('provider')->default('local');
            $table->string('preferred_language')->default('fr');
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('referral_code')->unique();
            $table->integer('default_commission_rate')->default(1000);
            $table->integer('tax_rate')->default(0);
            $table->string('status')->default('active');
            $table->string('application_status')->default('pending');
            $table->text('payment_details')->nullable();
            $table->integer('payout_threshold')->default(5000);
            $table->string('payout_cycle')->default('monthly');
            $table->string('type')->default('individual');
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->uuid('company_id');
            $table->unsignedBigInteger('payment_id');
            $table->integer('amount');
            $table->integer('net_amount')->nullable();
            $table->string('currency', 3)->default('DZD');
            $table->integer('applied_rate');
            $table->decimal('exchange_rate', 15, 8)->default(1.0);
            $table->integer('original_amount')->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id');
            $table->integer('amount');
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->uuid('company_id');
            $table->timestamp('referred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('code');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_link_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer_url')->nullable();
            $table->timestamp('clicked_at')->nullable();
        });

        Schema::create('partner_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('auditable_type');
            $table->string('auditable_id'); // String for UUID support
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->json('break_rules')->nullable();
            $table->json('work_days')->nullable();
            $table->json('rest_days')->nullable();
            $table->json('leave_rules')->nullable();
            $table->text('assignment_notes')->nullable();
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
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->unsignedInteger('site_id')->nullable();
            $table->string('matricule', 20)->nullable();
            $table->string('zkteco_id', 50)->nullable();
            $table->string('first_name', 100)->default('');
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->default('');
            $table->string('preferred_name', 100)->nullable();
            $table->string('email', 150);
            $table->string('personal_email', 150)->nullable();
            $table->string('recovery_email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('personal_phone', 30)->nullable();
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
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('payment_method', 50)->default('bank_transfer');
            $table->string('role', 20)->default('employee');
            $table->string('manager_role', 30)->nullable();
            $table->unsignedInteger('manager_id')->nullable();
            $table->decimal('leave_balance', 6, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestampTz('archived_at')->nullable();
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
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
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
            $table->string('work_type', 30)->default('normal');
            $table->text('punch_note')->nullable();
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('punch_meta')->nullable();
            } else {
                $table->json('punch_meta')->nullable();
            }
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

        Schema::create($this->tenantTable('attendance_correction_requests'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedBigInteger('attendance_log_id')->nullable()->index();
            $table->date('date')->index();
            $table->timestampTz('requested_check_in');
            $table->timestampTz('requested_check_out')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
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

        Schema::create($this->tenantTable('audit_logs'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
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
            $table->timestampTz('manager_approved_at')->nullable();
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestampTz('payment_declared_at')->nullable();
            $table->unsignedBigInteger('payment_declared_by')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestampTz('employee_confirmed_at')->nullable();
            $table->string('validation_status', 32)->default('pending');
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

        Schema::create($this->tenantTable('features'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('key', 100)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->string('endpoint', 500);

            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('http_methods');
                $table->jsonb('parameters');
                $table->jsonb('response_schema');
                $table->jsonb('permissions');
                $table->jsonb('metadata');
            } else {
                $table->json('http_methods');
                $table->json('parameters');
                $table->json('response_schema');
                $table->json('permissions');
                $table->json('metadata');
            }

            $table->string('mobile_version_min', 20);
            $table->string('mobile_version_max', 20)->nullable();
            $table->string('api_version', 20);
            $table->string('status', 20)->default('active');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'api_version']);
            $table->unique(['company_id', 'key']);
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
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by');
            $table->json('assigned_to')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->timestampTz('due_date');
            $table->string('priority', 20)->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->unsignedSmallInteger('completed_minutes')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->string('recurrence_rule', 120)->nullable();
            $table->string('template_key', 100)->nullable();
            $table->string('status', 20)->default('todo');
            $table->string('category', 100)->nullable();
            $table->json('checklist')->nullable();
            $table->string('visibility', 20)->default('visible');
            $table->timestamps();
        });

        Schema::create($this->tenantTable('notifications'), function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->string('type', 100);
            $table->string('title', 200);
            $table->text('body');
            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('data')->nullable();
            } else {
                $table->json('data')->nullable();
            }
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['employee_id', 'is_read']);
            $table->index('created_at');
        });

        Schema::create($this->tenantTable('notification_preferences'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->unique();
            $table->boolean('app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->char('locale', 2)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('categories')->nullable();
            $table->json('quiet_hours')->nullable();
            $table->timestamps();
        });

        Schema::create($this->tenantTable('communication_events'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->nullable()->index();
            $table->unsignedInteger('notification_id')->nullable()->index();
            $table->string('event_name', 80);
            $table->string('channel', 40)->default('app');
            $table->string('status', 40)->default('recorded');
            $table->string('provider', 80)->nullable();
            $table->string('template_key', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create($this->tenantTable('cabinet_folders'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('parent_id')->nullable()->index();
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create($this->tenantTable('cabinet_documents'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('folder_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->text('notes')->nullable();
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

        $this->createPostSprintModuleTables();
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
        DB::statement('DROP TABLE IF EXISTS public.partner_referrals CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.commissions CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.partner_payout_requests CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.partners CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.companies CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.users CASCADE');
        DB::statement('DROP TABLE IF EXISTS public.plans CASCADE');
        DB::statement('DROP TABLE IF EXISTS shared_tenants.features CASCADE');
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

    private function moduleTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }

    private function createPostSprintModuleTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->setPostgresSearchPath('shared_tenants,public');
        }

        if (! Schema::hasTable($this->moduleTable('subscriptions'))) {
            Schema::create($this->moduleTable('subscriptions'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('plan', 50)->default('trial');
                $table->string('status', 20)->default('trial');
                $table->timestampTz('trial_ends_at')->nullable();
                $table->timestampTz('current_period_start')->nullable();
                $table->timestampTz('current_period_end')->nullable();
                $table->timestampTz('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->string('payment_method', 30)->default('manual');
                $table->string('stripe_subscription_id', 100)->nullable();
                $table->string('chargily_subscription_id', 100)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('invoices'))) {
            Schema::create($this->moduleTable('invoices'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('number', 30)->unique();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->string('status', 20)->default('draft');
                $table->date('due_date');
                $table->timestampTz('paid_at')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('stripe_invoice_id', 100)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('payments'))) {
            Schema::create($this->moduleTable('payments'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('invoice_id');
                $table->uuid('company_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->string('method', 30)->default('manual');
                $table->string('provider_reference', 200)->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestampTz('paid_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('ai_conversations'))) {
            Schema::create($this->moduleTable('ai_conversations'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->string('title', 200)->default('Nouvelle conversation');
                $table->json('messages')->nullable();
                $table->json('context')->nullable();
                $table->unsignedInteger('token_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('ai_audit_logs'))) {
            Schema::create($this->moduleTable('ai_audit_logs'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->text('prompt');
                $table->text('response');
                $table->json('tools_called')->nullable();
                $table->string('provider', 50);
                $table->string('model', 100)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('cost_cents')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->text('error')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('ai_tool_registry'))) {
            Schema::create($this->moduleTable('ai_tool_registry'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name', 100)->unique();
                $table->text('description');
                $table->json('parameters')->nullable();
                $table->json('required_permissions')->nullable();
                $table->string('required_role', 20)->default('employee');
                $table->string('module', 50)->default('rh');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('client_events'))) {
            Schema::create($this->moduleTable('client_events'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->string('event_name', 80);
                $table->string('surface', 40)->default('web');
                $table->string('session_id', 120)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->timestamps();
                $table->index(['company_id', 'event_name', 'occurred_at']);
            });
        }

        if (! Schema::hasTable($this->moduleTable('notification_preferences'))) {
            Schema::create($this->moduleTable('notification_preferences'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->unique();
                $table->boolean('app_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('push_enabled')->default(true);
                $table->boolean('sms_enabled')->default(false);
                $table->boolean('whatsapp_enabled')->default(false);
                $table->char('locale', 2)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->json('categories')->nullable();
                $table->json('quiet_hours')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('communication_events'))) {
            Schema::create($this->moduleTable('communication_events'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->unsignedInteger('notification_id')->nullable()->index();
                $table->string('event_name', 80);
                $table->string('channel', 40)->default('app');
                $table->string('status', 40)->default('recorded');
                $table->string('provider', 80)->nullable();
                $table->string('template_key', 120)->nullable();
                $table->json('metadata')->nullable();
                $table->text('error_message')->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('webhook_endpoints'))) {
            Schema::create($this->moduleTable('webhook_endpoints'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('url', 500);
                $table->json('events');
                $table->text('secret');
                $table->boolean('active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestampTz('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('webhook_deliveries'))) {
            Schema::create($this->moduleTable('webhook_deliveries'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id');
                $table->string('event', 100);
                $table->json('payload');
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestampTz('delivered_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('payroll_runs'))) {
            Schema::create($this->moduleTable('payroll_runs'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->char('country_code', 2);
                $table->string('status', 20)->default('draft');
                $table->decimal('total_gross', 12, 2)->default(0);
                $table->decimal('total_deductions', 12, 2)->default(0);
                $table->decimal('total_net', 12, 2)->default(0);
                $table->decimal('total_employer_cost', 12, 2)->default(0);
                $table->unsignedInteger('employee_count')->default(0);
                $table->timestampTz('calculated_at')->nullable();
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestampTz('validated_at')->nullable();
                $table->timestampTz('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('salary_structures'))) {
            Schema::create($this->moduleTable('salary_structures'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 150);
                $table->string('code', 50);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn($this->moduleTable('employees'), 'salary_structure_id')) {
            Schema::table($this->moduleTable('employees'), function (Blueprint $table): void {
                $table->unsignedBigInteger('salary_structure_id')->nullable();
            });
        }

        if (! Schema::hasTable($this->moduleTable('contracts'))) {
            Schema::create($this->moduleTable('contracts'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->string('contract_type', 30)->default('cdi');
                $table->string('reference', 50)->nullable();
                $table->string('status', 20)->default('active');
                $table->string('job_title', 150)->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedInteger('position_id')->nullable();
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->string('salary_frequency', 20)->default('monthly');
                $table->decimal('work_hours_per_week', 5, 2)->default(40);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->date('trial_end_date')->nullable();
                $table->json('benefits')->nullable();
                $table->json('clauses')->nullable();
                $table->timestampTz('signed_at')->nullable();
                $table->string('signed_document_path', 500)->nullable();
                $table->text('termination_reason')->nullable();
                $table->timestampTz('terminated_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('contract_amendments'))) {
            Schema::create($this->moduleTable('contract_amendments'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('contract_id')->index();
                $table->uuid('company_id')->index();
                $table->string('amendment_type', 40)->default('other');
                $table->json('changes');
                $table->date('effective_date');
                $table->text('reason')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->string('document_path', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('privacy_requests'))) {
            Schema::create($this->moduleTable('privacy_requests'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->string('type', 40)->index();
                $table->string('status', 30)->default('received')->index();
                $table->json('requested_payload')->nullable();
                $table->timestampTz('processed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'type', 'status']);
                $table->index(['employee_id', 'type']);
            });
        }

        if (! Schema::hasTable($this->moduleTable('pay_slips'))) {
            Schema::create($this->moduleTable('pay_slips'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('payroll_run_id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_salary', 12, 2)->default(0);
                $table->decimal('total_deductions', 12, 2)->default(0);
                $table->decimal('net_salary', 12, 2)->default(0);
                $table->decimal('employer_contributions', 12, 2)->default(0);
                $table->decimal('total_cost', 12, 2)->default(0);
                $table->decimal('working_days', 5, 2)->default(0);
                $table->decimal('actual_days_worked', 5, 2)->default(0);
                $table->decimal('overtime_hours', 6, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->string('pdf_path', 500)->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('payment_documents'))) {
            Schema::create($this->moduleTable('payment_documents'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->unsignedBigInteger('payroll_run_id')->nullable()->index();
                $table->unsignedBigInteger('pay_slip_id')->nullable()->index();
                $table->unsignedInteger('salary_advance_id')->nullable()->index();
                $table->string('document_type', 40)->index();
                $table->string('status', 20)->default('pending')->index();
                $table->string('disk', 40)->default('local');
                $table->string('path', 500)->nullable();
                $table->string('filename', 255)->nullable();
                $table->string('mime_type', 80)->default('application/pdf');
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('requested_by')->nullable()->index();
                $table->timestampTz('generated_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'document_type', 'status']);
                $table->index(['employee_id', 'document_type', 'created_at']);
            });
        }

        if (! Schema::hasTable($this->moduleTable('payment_batches'))) {
            Schema::create($this->moduleTable('payment_batches'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('payroll_run_id')->nullable()->index();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->char('currency', 3)->default('DZD');
                $table->unsignedInteger('items_count')->default(0);
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->unsignedInteger('marked_paid_by')->nullable()->index();
                $table->timestampTz('marked_paid_at')->nullable();
                $table->timestampTz('confirmed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('payment_items'))) {
            Schema::create($this->moduleTable('payment_items'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('payment_batch_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->unsignedBigInteger('pay_slip_id')->nullable()->index();
                $table->unsignedInteger('salary_advance_id')->nullable()->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->char('currency', 3)->default('DZD');
                $table->string('status', 30)->default('pending')->index();
                $table->timestampTz('paid_at')->nullable();
                $table->timestampTz('confirmed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('payment_confirmations'))) {
            Schema::create($this->moduleTable('payment_confirmations'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('payment_batch_id')->index();
                $table->unsignedBigInteger('payment_item_id')->unique();
                $table->unsignedInteger('employee_id')->index();
                $table->string('status', 30)->default('confirmed')->index();
                $table->timestampTz('confirmed_at');
                $table->string('device_signature', 255)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('document_version', 40)->default('v1');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('pay_slip_lines'))) {
            Schema::create($this->moduleTable('pay_slip_lines'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pay_slip_id');
                $table->unsignedBigInteger('salary_component_id')->nullable();
                $table->string('name', 150);
                $table->string('type', 30);
                $table->decimal('base_amount', 14, 2)->default(0);
                $table->decimal('rate', 8, 4)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->unsignedSmallInteger('order')->default(0);
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('job_postings'))) {
            Schema::create($this->moduleTable('job_postings'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->unsignedInteger('position_id')->nullable();
                $table->string('location', 200)->nullable();
                $table->string('remote_policy', 20)->nullable();
                $table->string('contract_type', 20)->nullable();
                $table->decimal('salary_range_min', 12, 2)->nullable();
                $table->decimal('salary_range_max', 12, 2)->nullable();
                $table->string('currency', 3)->default('DZD');
                $table->json('skills_required')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestampTz('published_at')->nullable();
                $table->timestampTz('closes_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('applicants'))) {
            Schema::create($this->moduleTable('applicants'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('job_posting_id');
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email');
                $table->string('phone', 30)->nullable();
                $table->string('resume_path', 500)->nullable();
                $table->text('cover_letter')->nullable();
                $table->string('source', 30)->nullable();
                $table->string('status', 20)->default('new');
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('notes')->nullable();
                $table->timestampTz('applied_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('interviews'))) {
            Schema::create($this->moduleTable('interviews'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('applicant_id');
                $table->unsignedInteger('interviewer_id')->nullable();
                $table->string('type', 20);
                $table->timestampTz('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->text('feedback')->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('training_courses'))) {
            Schema::create($this->moduleTable('training_courses'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('category', 100)->nullable();
                $table->string('type', 30)->default('internal');
                $table->string('provider', 200)->nullable();
                $table->decimal('duration_hours', 6, 2)->nullable();
                $table->unsignedSmallInteger('max_participants')->nullable();
                $table->decimal('cost_per_participant', 12, 2)->nullable();
                $table->string('currency', 3)->default('DZD');
                $table->string('materials_path', 500)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('training_sessions'))) {
            Schema::create($this->moduleTable('training_sessions'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_course_id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('trainer_id')->nullable();
                $table->string('external_trainer', 200)->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('location', 200)->nullable();
                $table->string('status', 20)->default('planned');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('training_enrollments'))) {
            Schema::create($this->moduleTable('training_enrollments'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_session_id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->string('status', 20)->default('enrolled');
                $table->decimal('score', 5, 2)->nullable();
                $table->string('certificate_path', 500)->nullable();
                $table->text('feedback')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('employee_loans'))) {
            Schema::create($this->moduleTable('employee_loans'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->string('loan_type', 30)->default('personal');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->unsignedSmallInteger('installments');
                $table->decimal('installment_amount', 12, 2);
                $table->date('start_date');
                $table->string('status', 30)->default('draft');
                $table->unsignedInteger('approved_by')->nullable();
                $table->timestampTz('disbursed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('loan_repayments'))) {
            Schema::create($this->moduleTable('loan_repayments'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_loan_id');
                $table->uuid('company_id')->index();
                $table->date('due_date');
                $table->decimal('amount', 12, 2);
                $table->decimal('principal', 12, 2);
                $table->decimal('interest', 12, 2)->default(0);
                $table->string('status', 20)->default('pending');
                $table->timestampTz('paid_at')->nullable();
                $table->unsignedInteger('payroll_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('expense_claims'))) {
            Schema::create($this->moduleTable('expense_claims'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->string('status', 20)->default('draft');
                $table->timestampTz('submitted_at')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->timestampTz('paid_at')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('expense_items'))) {
            Schema::create($this->moduleTable('expense_items'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('expense_claim_id');
                $table->string('category', 30)->default('other');
                $table->string('description', 255);
                $table->decimal('amount', 12, 2);
                $table->date('date');
                $table->string('receipt_path', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('vehicles'))) {
            Schema::create($this->moduleTable('vehicles'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('plate_number', 20);
                $table->string('brand', 100)->nullable();
                $table->string('model', 100)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('type', 30)->nullable();
                $table->string('vin', 17)->nullable();
                $table->string('fuel_type', 30)->nullable();
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('mileage')->nullable();
                $table->date('insurance_expiry')->nullable();
                $table->date('technical_control_expiry')->nullable();
                $table->unsignedBigInteger('traccar_device_id')->nullable();
                $table->string('traccar_unique_id', 50)->nullable();
                $table->unsignedInteger('assigned_driver_id')->nullable();
                $table->unsignedInteger('assigned_site_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($this->moduleTable('vehicle_assignments'))) {
            Schema::create($this->moduleTable('vehicle_assignments'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedInteger('created_by');
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('vehicle_trips'))) {
            Schema::create($this->moduleTable('vehicle_trips'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('driver_id')->nullable();
                $table->timestampTz('start_time')->nullable();
                $table->timestampTz('end_time')->nullable();
                $table->decimal('start_lat', 10, 7)->nullable();
                $table->decimal('start_lng', 10, 7)->nullable();
                $table->string('start_address')->nullable();
                $table->decimal('end_lat', 10, 7)->nullable();
                $table->decimal('end_lng', 10, 7)->nullable();
                $table->string('end_address')->nullable();
                $table->decimal('distance_km', 10, 2)->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->decimal('max_speed_kmh', 8, 2)->nullable();
                $table->decimal('avg_speed_kmh', 8, 2)->nullable();
                $table->decimal('fuel_consumed', 8, 2)->nullable();
                $table->string('traccar_trip_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('vehicle_alerts'))) {
            Schema::create($this->moduleTable('vehicle_alerts'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->index();
                $table->string('type', 50);
                $table->string('message');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed', 8, 2)->nullable();
                $table->boolean('acknowledged')->default(false);
                $table->unsignedInteger('acknowledged_by')->nullable();
                $table->string('traccar_event_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable($this->moduleTable('vehicle_maintenances'))) {
            Schema::create($this->moduleTable('vehicle_maintenances'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_id');
                $table->uuid('company_id')->index();
                $table->string('type', 50);
                $table->text('description')->nullable();
                $table->decimal('cost', 12, 2)->nullable();
                $table->string('currency', 3)->default('DZD');
                $table->unsignedInteger('mileage_at_service')->nullable();
                $table->date('service_date')->nullable();
                $table->date('next_service_date')->nullable();
                $table->unsignedInteger('next_service_mileage')->nullable();
                $table->string('provider')->nullable();
                $table->string('invoice_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_sso_configs')) {
            Schema::create('company_sso_configs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('provider', 20);
                $table->jsonb('config')->default('{}');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
                $table->unique('company_id');
            });
        }

        if (! Schema::hasTable($this->moduleTable('device_tokens'))) {
            Schema::create($this->moduleTable('device_tokens'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->string('token', 512);
                $table->string('platform', 20);
                $table->string('device_name', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestampTz('last_used_at')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'token']);
            });
        }

        if (! Schema::hasTable($this->moduleTable('calendar_connections'))) {
            Schema::create($this->moduleTable('calendar_connections'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('provider', 20);
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->string('calendar_id')->nullable();
                $table->timestampTz('token_expires_at')->nullable();
                $table->boolean('sync_leaves')->default(true);
                $table->boolean('sync_training')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestampTz('last_synced_at')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'provider']);
            });
        }

        if (! Schema::hasTable($this->moduleTable('calendar_events'))) {
            Schema::create($this->moduleTable('calendar_events'), function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('external_event_id')->nullable();
                $table->string('provider', 20)->default('google');
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestampTz('starts_at');
                $table->timestampTz('ends_at');
                $table->boolean('all_day')->default(false);
                $table->string('source_type', 30);
                $table->unsignedBigInteger('source_id');
                $table->string('sync_status', 20)->default('pending');
                $table->timestamps();
                $table->index(['employee_id', 'starts_at']);
                $table->index('sync_status');
            });
        }

        if (! Schema::hasTable($this->moduleTable('zkteco_devices'))) {
            Schema::create($this->moduleTable('zkteco_devices'), function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('serial_number', 100)->unique();
                $table->string('name', 100);
                $table->string('ip_address', 45)->nullable();
                $table->unsignedSmallInteger('port')->default(4370);
                $table->string('protocol', 20)->default('tcp');
                $table->string('status', 20)->default('offline');
                $table->timestampTz('last_heartbeat_at')->nullable();
                $table->timestampTz('last_sync_at')->nullable();
                $table->timestamps();
            });
        }
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
        DB::statement('DROP TABLE IF EXISTS "attendance_correction_requests"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_access_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_permissions"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "camera_access_tokens"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "cameras"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "zkteco_devices"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "calendar_events"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "calendar_connections"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "device_tokens"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "company_sso_configs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "vehicle_maintenances"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "vehicle_alerts"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "vehicle_trips"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "vehicle_assignments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "vehicles"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "expense_items"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "expense_claims"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "loan_repayments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "employee_loans"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "training_enrollments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "training_sessions"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "training_courses"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "interviews"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "applicants"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "job_postings"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "pay_slip_lines"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payment_confirmations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payment_items"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payment_batches"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payment_documents"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "pay_slips"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "privacy_requests"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "contract_amendments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "contracts"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "salary_structures"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payroll_runs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "communication_events"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "notification_preferences"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "client_events"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "ai_audit_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "ai_conversations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "ai_tool_registry"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "payments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "invoices"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "subscriptions"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "user_lookups"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_correction_requests"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "attendance_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "salary_advances"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "evaluations"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "audit_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "features"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "leave_balance_logs"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "absences"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "notifications"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "tasks"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "projects"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "sites"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "positions"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "departments"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "cabinet_documents"'.$cascade);
        DB::statement('DROP TABLE IF EXISTS "cabinet_folders"'.$cascade);
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
        $defaultConnection = config('database.default', 'pgsql');
        $connection = is_string($defaultConnection) && $defaultConnection !== ''
            ? $defaultConnection
            : 'pgsql';

        config(["database.connections.{$connection}.search_path" => $path]);

        DB::purge($connection);
        DB::reconnect($connection);
        DB::statement("SET search_path TO {$path}");
    }
}
