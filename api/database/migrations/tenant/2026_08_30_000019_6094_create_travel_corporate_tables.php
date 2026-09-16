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

        if (schemaTableExists('travel_quotes')) {
            // Issue #7452 — la table est créée par 2026_08_30_000006_6094_create_travel_quotes_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_quotes', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_quotes', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_quotes', 'corporate_account_id')) {
                    $table->unsignedBigInteger('corporate_account_id')->nullable();
                }
                if (! schemaHasColumn('travel_quotes', 'trip_id')) {
                    $table->unsignedBigInteger('trip_id');
                }
                if (! schemaHasColumn('travel_quotes', 'class_id')) {
                    $table->unsignedBigInteger('class_id')->nullable();
                }
                if (! schemaHasColumn('travel_quotes', 'passengers_count')) {
                    $table->unsignedInteger('passengers_count')->nullable();
                }
                if (! schemaHasColumn('travel_quotes', 'total_amount_minor')) {
                    $table->unsignedBigInteger('total_amount_minor');
                }
                if (! schemaHasColumn('travel_quotes', 'currency')) {
                    $table->char('currency', 3);
                }
                if (! schemaHasColumn('travel_quotes', 'status')) {
                    $table->string('status', 20)->default('draft');
                }
                if (! schemaHasColumn('travel_quotes', 'expires_at')) {
                    // draft|accepted|cancelled|expired
                    $table->timestamp('expires_at')->nullable();
                }
                if (! schemaHasColumn('travel_quotes', 'created_by_user_id')) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_quotes', 'created_at')) {
                    $table->timestamps();
                }
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

        if (schemaHasColumn('travel_quotes', 'company_id')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_quotes', 'corporate_account_id')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('corporate_account_id');
            });
        }
        if (schemaHasColumn('travel_quotes', 'trip_id')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('trip_id');
            });
        }
        if (schemaHasColumn('travel_quotes', 'class_id')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('class_id');
            });
        }
        if (schemaHasColumn('travel_quotes', 'passengers_count')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('passengers_count');
            });
        }
        if (schemaHasColumn('travel_quotes', 'total_amount_minor')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('total_amount_minor');
            });
        }
        if (schemaHasColumn('travel_quotes', 'currency')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        if (schemaHasColumn('travel_quotes', 'status')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_quotes', 'expires_at')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('expires_at');
            });
        }
        if (schemaHasColumn('travel_quotes', 'created_by_user_id')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('created_by_user_id');
            });
        }
        if (schemaHasColumn('travel_quotes', 'created_at')) {
            Schema::table('travel_quotes', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        Schema::dropIfExists('travel_corporate_accounts');
    }
};
