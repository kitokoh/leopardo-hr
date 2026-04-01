<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 0003 — Tables publiques : super_admins, user_lookups, languages,
 *                                      hr_model_templates, invoices, billing_transactions
 *
 * Schéma : public
 * Dépendances : companies (0002)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET search_path TO public");

        // ── super_admins ───────────────────────────────────────────────────────
        // Guard Sanctum dédié (super_admin_tokens) — séparé des employees
        Schema::create('super_admins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('two_fa_secret', 32)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── user_lookups ───────────────────────────────────────────────────────
        // Table de dispatch auth multi-tenant : email → company + schema
        // Critique pour performance : 1 seule query pour router vers le bon schéma
        Schema::create('user_lookups', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->uuid('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->string('schema_name', 60);                      // Dupliqué pour éviter JOIN
            $table->unsignedInteger('employee_id');                 // ID dans le schéma tenant
            $table->string('role', 20);                             // Cache du rôle (lecture rapide)

            $table->index('company_id');
        });

        DB::statement("COMMENT ON TABLE user_lookups IS 'Table de dispatch auth. email → company_id + schema_name en O(1). Mise à jour par TenantService à chaque création/archivage employé'");

        // ── languages ──────────────────────────────────────────────────────────
        Schema::create('languages', function (Blueprint $table) {
            $table->increments('id');
            $table->char('code', 2)->unique();                      // 'fr', 'ar', 'en', 'tr'
            $table->string('name_fr', 50);                          // 'Français', 'Arabe'...
            $table->string('name_native', 50);                      // 'Français', 'العربية'...
            $table->boolean('is_rtl')->default(false);              // true pour 'ar'
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── hr_model_templates ─────────────────────────────────────────────────
        // Modèles RH préconfigurés par pays (cotisations, tranches IR, congés)
        Schema::create('hr_model_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->char('country_code', 2)->unique();              // 'DZ', 'MA', 'TN', 'FR', 'TR'
            $table->string('name', 100);                            // 'Modèle Algérie'
            $table->jsonb('cotisations')->default('{}');
            // Structure: {salariales:[{name,rate,base,ceiling}], patronales:[...]}
            $table->jsonb('ir_brackets')->default('[]');
            // Structure: [{min,max,rate,deduction}]
            $table->jsonb('leave_rules')->default('{}');
            // Structure: {accrual_rate_monthly, initial_balance, carry_over, max_balance}
            $table->jsonb('holiday_calendar')->default('[]');
            // Structure: [{date, name, is_recurring}]
            $table->jsonb('working_hours')->default('{}');
            // Structure: {weekly_hours, daily_hours, overtime_threshold_daily, overtime_threshold_weekly}
        });

        // ── invoices ───────────────────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->string('period', 20);                           // ex: '2026-04'
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->string('pdf_path', 255)->nullable();
            $table->date('due_date');
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();       // 'stripe', 'cmi', 'paydunya', 'manual'
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('company_id');
            $table->index('status');
            $table->index('due_date');
        });

        // ── billing_transactions ───────────────────────────────────────────────
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id')->references('id')->on('invoices');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('gateway', 50)->nullable();              // 'stripe', 'cmi', 'paydunya', 'manual'
            $table->string('gateway_ref', 100)->nullable();         // ID transaction chez le gateway
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->jsonb('gateway_payload')->nullable();           // Raw webhook payload (debug)
            $table->timestampTz('created_at')->useCurrent();

            $table->index('invoice_id');
        });

        // ── personal_access_tokens (Sanctum) ──────────────────────────────────
        // Table standard Sanctum — gère DEUX types de tokens :
        // 1. tokenable_type = 'App\Models\Public\SuperAdmin' → routes /admin/*
        // 2. tokenable_type = 'App\Models\Tenant\Employee'  → routes /api/v1/*
        // La migration Sanctum standard est utilisée telle quelle.
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('hr_model_templates');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('user_lookups');
        Schema::dropIfExists('super_admins');
    }
};
