<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('plan', 50)->default('trial');
                $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])->default('trial');
                $table->timestampTz('trial_ends_at')->nullable();
                $table->timestampTz('current_period_start')->nullable();
                $table->timestampTz('current_period_end')->nullable();
                $table->timestampTz('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->enum('payment_method', ['stripe', 'chargily', 'bank_transfer', 'manual'])->default('manual');
                $table->string('stripe_subscription_id', 100)->nullable();
                $table->string('chargily_subscription_id', 100)->nullable();
                $table->timestampsTz();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->string('number', 30)->unique();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
                $table->date('due_date');
                $table->timestampTz('paid_at')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('stripe_invoice_id', 100)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->timestampsTz();

                $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('invoice_id');
                $table->uuid('company_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->enum('method', ['card', 'cib', 'edahabia', 'bank_transfer', 'manual'])->default('manual');
                $table->string('provider_reference', 200)->nullable();
                $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
                $table->timestampTz('paid_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('onboarding_steps')) {
            Schema::create('onboarding_steps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('step_key', 50);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
                $table->timestampTz('completed_at')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->unsignedSmallInteger('order')->default(0);
                $table->boolean('required')->default(true);
                $table->jsonb('metadata')->default('{}');
                $table->timestampsTz();

                $table->unique(['company_id', 'step_key']);
            });
        }

        if (! Schema::hasTable('feature_plan_matrix')) {
            Schema::create('feature_plan_matrix', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('feature_key', 50);
                $table->string('plan', 50);
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('limit_value')->nullable();
                $table->timestampsTz();

                $table->unique(['feature_key', 'plan']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_plan_matrix');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
