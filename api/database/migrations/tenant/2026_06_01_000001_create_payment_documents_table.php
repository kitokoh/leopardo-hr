<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_documents')) {
            return;
        }

        Schema::create('payment_documents', function (Blueprint $table): void {
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

    public function down(): void
    {
        Schema::dropIfExists('payment_documents');
    }
};
