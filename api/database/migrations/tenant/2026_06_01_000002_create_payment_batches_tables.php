<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_batches')) {
            Schema::create('payment_batches', function (Blueprint $table): void {
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

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('payment_items')) {
            Schema::create('payment_items', function (Blueprint $table): void {
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

                $table->unique(['payment_batch_id', 'employee_id', 'pay_slip_id'], 'payment_items_batch_employee_slip_unique');
                $table->index(['company_id', 'employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('payment_confirmations')) {
            Schema::create('payment_confirmations', function (Blueprint $table): void {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_confirmations');
        Schema::dropIfExists('payment_items');
        Schema::dropIfExists('payment_batches');
    }
};
