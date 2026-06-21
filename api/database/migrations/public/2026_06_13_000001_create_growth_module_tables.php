<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code')->unique();
            $table->integer('default_commission_rate')->default(1000); // In basis points (e.g., 1000 = 10.00%)
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('type')->default('individual');
            $table->timestamps();
        });

        Schema::create('partner_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->uuid('company_id')->unique();
            $table->timestamp('referred_at')->useCurrent();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->uuid('company_id');
            $table->unsignedBigInteger('payment_id');
            $table->integer('amount'); // In cents
            $table->string('currency', 3)->default('DZD');
            $table->integer('applied_rate'); // Snapshot of commission rate in basis points
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('partner_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('auditable_type');
            $table->string('auditable_id'); // UUID or Numeric ID as String
            $table->string('event');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('admin_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_audit_logs');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('partner_referrals');
        Schema::dropIfExists('partners');
    }
};
