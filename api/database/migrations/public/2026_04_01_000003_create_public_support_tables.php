<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 0003 - Tables publiques de support
 *
 * Schema : public
 * Dependances : companies (0002)
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("SET search_path TO public");

        $this->createIfMissing('super_admins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('two_fa_secret', 32)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        $this->createIfMissing('user_lookups', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->uuid('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->string('schema_name', 63);
            $table->unsignedInteger('employee_id');
            $table->string('role', 20);
            $table->index('company_id');
        });

        if (Schema::hasTable('user_lookups')) {
            DB::statement("COMMENT ON TABLE user_lookups IS 'Table de dispatch auth. email -> company_id + schema_name en O(1). Mise a jour par TenantService a chaque creation/archivage employe'");
        }

        $this->createIfMissing('languages', function (Blueprint $table) {
            $table->increments('id');
            $table->char('code', 2)->unique();
            $table->string('name_fr', 50);
            $table->string('name_native', 50);
            $table->boolean('is_rtl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        $this->createIfMissing('hr_model_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->char('country_code', 2)->unique();
            $table->string('name', 100);
            $table->jsonb('cotisations')->default('{}');
            $table->jsonb('ir_brackets')->default('[]');
            $table->jsonb('leave_rules')->default('{}');
            $table->jsonb('holiday_calendar')->default('[]');
            $table->jsonb('working_hours')->default('{}');
        });

        $this->createIfMissing('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->string('period', 20);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->string('pdf_path', 255)->nullable();
            $table->date('due_date');
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('company_id');
            $table->index('status');
            $table->index('due_date');
        });

        $this->createIfMissing('billing_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id')->references('id')->on('invoices');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_ref', 100)->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->jsonb('gateway_payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('invoice_id');
        });
    }

    private function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::create($table, $callback);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '42P07') {
                throw $exception;
            }
        }
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
