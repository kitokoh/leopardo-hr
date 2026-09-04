<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6174 (RESTO-209) - RestaurantManager : paiements, remboursements, sessions de table.
 *
 * - `restaurant_order_payments` : paiements d une commande (provider_code, montant en
 *   minor units, idempotency_key unique, callback redige `_redacted` en json) ;
 * - `restaurant_refunds` : remboursements (reason_code, idempotency_key unique) ;
 * - `restaurant_table_sessions` : occupation d une table (ouverture/fermeture, statut).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_order_payments')) {
            Schema::create('restaurant_order_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('pos_session_id')->nullable();
                $table->string('provider_code', 20);
                $table->unsignedInteger('amount_minor');
                $table->char('currency', 3)->default('DZD');
                $table->string('status', 20)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->string('provider_reference', 120)->nullable();
                $table->unsignedInteger('tip_minor')->nullable();
                $table->json('callback_payload_redacted')->nullable();
                $table->string('idempotency_key', 64)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'restaurant_order_payments_company_idempotency_key_unique');
                $table->index(['company_id', 'order_id'], 'restaurant_order_payments_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_order_payments IS 'Paiements de commande - idempotency_key unique par tenant, callback redige en json (RESTO-209/#6174).';");
        }

        if (! schemaTableExists('restaurant_refunds')) {
            Schema::create('restaurant_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->unsignedInteger('amount_minor');
                $table->string('reason_code', 30);
                $table->text('reason_text_redacted')->nullable();
                $table->unsignedBigInteger('refunded_by_user_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('idempotency_key', 64)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'restaurant_refunds_company_idempotency_key_unique');
                $table->index(['company_id', 'order_id'], 'restaurant_refunds_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_refunds IS 'Remboursements - idempotency_key unique par tenant, montants en minor units (RESTO-209/#6174).';");
        }

        if (! schemaTableExists('restaurant_table_sessions')) {
            Schema::create('restaurant_table_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('table_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedSmallInteger('covers')->nullable();
                $table->string('status', 20)->default('open');

                $table->timestamps();

                $table->index(['company_id', 'table_id'], 'restaurant_table_sessions_company_table_idx');
                $table->index(['company_id', 'branch_id', 'status'], 'restaurant_table_sessions_company_branch_status_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_table_sessions IS 'Occupation des tables - ouverture/fermeture de session par table (RESTO-209/#6174).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_table_sessions');
        Schema::dropIfExists('restaurant_refunds');
        Schema::dropIfExists('restaurant_order_payments');
    }
};
