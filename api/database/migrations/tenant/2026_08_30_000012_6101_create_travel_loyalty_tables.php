<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6101 (TRAVEL-811) — Fidélité voyageur.
 *
 * - `travel_loyalty_accounts` : solde par contact (email/téléphone), opt-in
 *   RGPD explicite (aucun crédit sans opt-in — critère d'acceptation).
 * - `travel_loyalty_entries` : journal des points (crédits/débits) — la
 *   contrainte unique (company, ticket) garantit un crédit UNIQUE par
 *   billet ; (company, booking, type) rend le débit de récompense
 *   idempotent.
 * - `travel_loyalty_rewards` : catalogue de récompenses (coût en points).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_loyalty_accounts')) {
            Schema::create('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('contact_identifier', 255);
                $table->integer('points_balance')->default(0);
                $table->boolean('opt_in')->default(false);
                $table->timestamp('opt_in_at')->nullable();
                $table->timestamp('opt_out_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'contact_identifier'], 'travel_loyalty_accounts_company_contact_unique');
            });
        }

        if (! schemaTableExists('travel_loyalty_entries')) {
            Schema::create('travel_loyalty_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('ticket_id')->nullable();
                $table->integer('points');
                $table->string('type', 20);
                $table->string('reason', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['company_id', 'ticket_id'], 'travel_loyalty_entries_company_ticket_unique');
                $table->unique(['company_id', 'booking_id', 'type'], 'travel_loyalty_entries_company_booking_type_unique');
            });
        }

        if (! schemaTableExists('travel_loyalty_rewards')) {
            Schema::create('travel_loyalty_rewards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 160);
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('points_cost');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_loyalty_rewards');
        Schema::dropIfExists('travel_loyalty_entries');
        Schema::dropIfExists('travel_loyalty_accounts');
    }
};
