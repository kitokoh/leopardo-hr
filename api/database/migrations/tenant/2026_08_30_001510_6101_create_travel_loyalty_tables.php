<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6101 (TRAVEL-811) — Fidélité voyageur.
 *
 * Compte par contact (opt-in RGPD explicite, horodaté) + transactions de
 * points. Un point n'est crédité qu'une seule fois par billet (unique
 * ticket_id sur les earn) — acceptance TRAVEL-811. Opt-out = plus aucun
 * crédit (le solde reste consultable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_loyalty_accounts')) {
            // Issue #7452 — la table est créée par 2026_08_30_000012_6101_create_travel_loyalty_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_loyalty_accounts', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_loyalty_accounts', 'contact_id')) {
                    $table->unsignedBigInteger('contact_id')->nullable();
                }
                if (! schemaHasColumn('travel_loyalty_accounts', 'points_balance')) {
                    $table->unsignedInteger('points_balance')->default(0);
                }
                if (! schemaHasColumn('travel_loyalty_accounts', 'opt_in_at')) {
                    $table->timestamp('opt_in_at')->nullable();
                }
                if (! schemaHasColumn('travel_loyalty_accounts', 'opt_out_at')) {
                    $table->timestamp('opt_out_at')->nullable();
                }
                if (! schemaHasColumn('travel_loyalty_accounts', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (! schemaTableExists('travel_loyalty_transactions')) {
            Schema::create('travel_loyalty_transactions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('account_id');
                $table->integer('points');
                $table->string('type', 10); // earn | burn
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('ticket_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'ticket_id'], 'travel_loyalty_transactions_company_ticket_unique');
                $table->index(['company_id', 'account_id'], 'travel_loyalty_transactions_company_account_idx');
            });

            DB::statement("ALTER TABLE travel_loyalty_transactions ADD CONSTRAINT travel_loyalty_transactions_type_check CHECK (type IN ('earn', 'burn'))");
            DB::statement("COMMENT ON TABLE travel_loyalty_transactions IS 'Transactions de points fidélité — earn unique par billet (TRAVEL-811/#6101).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_loyalty_transactions');
        if (schemaHasColumn('travel_loyalty_accounts', 'company_id')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_loyalty_accounts', 'contact_id')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('contact_id');
            });
        }
        if (schemaHasColumn('travel_loyalty_accounts', 'points_balance')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('points_balance');
            });
        }
        if (schemaHasColumn('travel_loyalty_accounts', 'opt_in_at')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('opt_in_at');
            });
        }
        if (schemaHasColumn('travel_loyalty_accounts', 'opt_out_at')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('opt_out_at');
            });
        }
        if (schemaHasColumn('travel_loyalty_accounts', 'created_at')) {
            Schema::table('travel_loyalty_accounts', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
