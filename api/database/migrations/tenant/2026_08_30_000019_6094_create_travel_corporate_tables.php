<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6094 (TRAVEL-803) — Réservations groupe / corporate.
 *
 * - `travel_corporate_accounts` : compte B2B avec PL AFOND (crédit en
 *   minor units, devise) — les réservations corporate cumulées ne peuvent
 *   pas dépasser le plafond.
 * - `travel_quotes` : devis (prix calculé SERVEUR), cycle
 *   draft → accepted → cancelled/expired.
 * - `travel_bookings` : colonnes corporate (compte, devis, facturation
 *   différée — le règlement passe par le contrat Accounting, TRAVEL-417).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_corporate_accounts')) {
            Schema::create('travel_corporate_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 160);
                $table->string('contact_email', 255)->nullable();
                $table->unsignedBigInteger('credit_limit_minor')->default(0);
                $table->char('currency', 3);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'travel_corporate_accounts_company_name_unique');
            });
        }

        if (! schemaTableExists('travel_quotes')) {
            Schema::create('travel_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('corporate_account_id');
                $table->unsignedBigInteger('trip_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedInteger('passengers_count');
                $table->unsignedBigInteger('total_amount_minor');
                $table->char('currency', 3);
                $table->string('status', 20)->default('draft'); // draft|accepted|cancelled|expired
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'corporate_account_id'], 'travel_quotes_company_account_idx');
            });
        }

        if (! schemaHasColumn('travel_bookings', 'corporate_account_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->unsignedBigInteger('corporate_account_id')->nullable();
                $table->unsignedBigInteger('quote_id')->nullable();
                $table->boolean('billing_deferred')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropColumn(['corporate_account_id', 'quote_id', 'billing_deferred']);
        });

        Schema::dropIfExists('travel_quotes');
        Schema::dropIfExists('travel_corporate_accounts');
    }
};
