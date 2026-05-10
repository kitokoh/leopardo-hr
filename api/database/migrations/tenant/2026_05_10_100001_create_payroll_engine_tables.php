<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('salary_structures')) {
            Schema::create('salary_structures', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->string('name', 150);
                $table->decimal('base_salary', 14, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->string('country_code', 2)->default('DZ');
                $table->enum('frequency', ['monthly', 'bi_weekly', 'weekly'])->default('monthly');
                $table->boolean('active')->default(true);
                $table->timestampsTz();

                $table->index(['company_id', 'country_code', 'active']);
            });
        }

        if (! Schema::hasTable('salary_components')) {
            Schema::create('salary_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedBigInteger('salary_structure_id')->nullable();
                $table->string('name', 150);
                $table->string('code', 50);
                $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
                $table->enum('calculation_type', ['fixed', 'percentage_of_base', 'percentage_of_gross', 'formula'])->default('fixed');
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('percentage', 8, 4)->nullable();
                $table->string('formula', 500)->nullable();
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_recurring')->default(true);
                $table->unsignedSmallInteger('order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestampsTz();

                $table->foreign('salary_structure_id')->references('id')->on('salary_structures')->nullOnDelete();
                $table->index(['company_id', 'type', 'active']);
                $table->unique(['company_id', 'code', 'salary_structure_id']);
            });
        }

        if (! Schema::hasTable('tax_slabs')) {
            Schema::create('tax_slabs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->string('country_code', 2);
                $table->string('name', 150);
                $table->decimal('min_amount', 14, 2);
                $table->decimal('max_amount', 14, 2)->nullable();
                $table->decimal('rate', 8, 4);
                $table->decimal('fixed_deduction', 14, 2)->default(0);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->timestampsTz();

                $table->index(['country_code', 'effective_from']);
            });
        }

        if (! Schema::hasTable('social_contributions')) {
            Schema::create('social_contributions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->string('country_code', 2);
                $table->string('name', 150);
                $table->string('code', 50)->unique();
                $table->enum('type', ['employee', 'employer']);
                $table->decimal('rate', 8, 4);
                $table->decimal('cap', 14, 2)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->timestampsTz();

                $table->index(['country_code', 'type', 'effective_from']);
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('country_code', 2)->default('DZ');
                $table->enum('status', ['draft', 'calculating', 'calculated', 'validated', 'paid', 'cancelled'])->default('draft');
                $table->decimal('total_gross', 16, 2)->default(0);
                $table->decimal('total_deductions', 16, 2)->default(0);
                $table->decimal('total_net', 16, 2)->default(0);
                $table->decimal('total_employer_cost', 16, 2)->default(0);
                $table->unsignedInteger('employee_count')->default(0);
                $table->timestampTz('calculated_at')->nullable();
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestampTz('validated_at')->nullable();
                $table->timestampTz('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestampsTz();

                $table->foreign('validated_by')->references('id')->on('employees')->nullOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'period_start', 'period_end']);
            });
        }

        if (! Schema::hasTable('pay_slips')) {
            Schema::create('pay_slips', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_run_id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('employee_id');
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_salary', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_salary', 14, 2)->default(0);
                $table->decimal('employer_contributions', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->decimal('working_days', 5, 2)->default(0);
                $table->decimal('actual_days_worked', 5, 2)->default(0);
                $table->decimal('overtime_hours', 6, 2)->default(0);
                $table->enum('status', ['draft', 'calculated', 'validated', 'sent'])->default('draft');
                $table->string('pdf_path', 500)->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->timestampsTz();

                $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->index(['company_id', 'employee_id', 'period_start']);
                $table->unique(['payroll_run_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('pay_slip_lines')) {
            Schema::create('pay_slip_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('pay_slip_id');
                $table->unsignedBigInteger('salary_component_id')->nullable();
                $table->string('name', 150);
                $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
                $table->decimal('base_amount', 14, 2)->default(0);
                $table->decimal('rate', 8, 4)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->unsignedSmallInteger('order')->default(0);
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('pay_slip_id')->references('id')->on('pay_slips')->cascadeOnDelete();
                $table->foreign('salary_component_id')->references('id')->on('salary_components')->nullOnDelete();
                $table->index(['pay_slip_id', 'type', 'order']);
            });
        }

        if (! Schema::hasTable('bank_exports')) {
            Schema::create('bank_exports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_run_id');
                $table->uuid('company_id')->nullable()->index();
                $table->enum('format', ['sepa_xml', 'ccp_dz', 'virement_ma', 'csv_generic'])->default('csv_generic');
                $table->string('file_path', 500);
                $table->decimal('total_amount', 16, 2)->default(0);
                $table->unsignedInteger('transfer_count')->default(0);
                $table->enum('status', ['generated', 'sent', 'confirmed'])->default('generated');
                $table->timestampTz('generated_at')->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->timestampsTz();

                $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_exports');
        Schema::dropIfExists('pay_slip_lines');
        Schema::dropIfExists('pay_slips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('social_contributions');
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('salary_structures');
    }
};
