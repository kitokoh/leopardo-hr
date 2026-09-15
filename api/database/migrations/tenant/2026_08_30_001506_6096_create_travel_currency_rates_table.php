<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6096 (TRAVEL-805) — travel_currency_rates : taux de conversion par tenant.
 *
 * Taux stocké en `rate_minor` = taux × 10000 (entier, 4 décimales) — les
 * conversions restent en math entière (aucune perte d'arrondi, spec §12).
 * Un taux est valide sur une période [valid_from, valid_to] ; les périodes
 * d'une même paire (from, to) ne doivent pas se chevaucher (validé
 * applicativement, pas de contrainte DB partielle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_currency_rates')) {
            // Issue #7452 — la table est créée par 2026_08_30_000020_6096_create_travel_currency_rates_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_currency_rates', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'from_currency')) {
                    $table->char('from_currency', 3)->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'to_currency')) {
                    $table->char('to_currency', 3)->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'rate_minor')) {
                    $table->unsignedBigInteger('rate_minor')->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'valid_from')) {
                    $table->date('valid_from')->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'valid_to')) {
                    $table->date('valid_to')->nullable();
                }
                if (! schemaHasColumn('travel_currency_rates', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_currency_rates', 'company_id')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'from_currency')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('from_currency');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'to_currency')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('to_currency');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'rate_minor')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('rate_minor');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'valid_from')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('valid_from');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'valid_to')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('valid_to');
            });
        }
        if (schemaHasColumn('travel_currency_rates', 'created_at')) {
            Schema::table('travel_currency_rates', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
