<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->enum('contract_type', ['cdi', 'cdd', 'stage', 'freelance', 'interim'])->default('cdi');
                $table->string('reference', 50)->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('job_title', 150)->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                $table->unsignedInteger('position_id')->nullable();
                $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->enum('salary_frequency', ['monthly', 'hourly', 'daily'])->default('monthly');
                $table->decimal('work_hours_per_week', 5, 2)->default(40);
                $table->date('probation_end_date')->nullable();
                $table->jsonb('benefits')->nullable();
                $table->jsonb('clauses')->nullable();
                $table->enum('status', ['draft', 'active', 'suspended', 'expired', 'terminated'])->default('draft');
                $table->timestampTz('signed_at')->nullable();
                $table->string('signed_document_path', 500)->nullable();
                $table->text('termination_reason')->nullable();
                $table->timestampTz('terminated_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampsTz();

                $table->index(['company_id', 'status']);
                $table->index(['employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('contract_amendments')) {
            Schema::create('contract_amendments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id');
                $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
                $table->uuid('company_id')->index();
                $table->enum('amendment_type', ['salary_change', 'position_change', 'hours_change', 'renewal', 'other'])->default('other');
                $table->jsonb('changes');
                $table->date('effective_date');
                $table->text('reason')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
                $table->string('document_path', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contracts');
    }
};
