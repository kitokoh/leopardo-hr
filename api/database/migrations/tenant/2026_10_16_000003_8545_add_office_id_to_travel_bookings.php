<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8545 (TRAVEL-OS) — rattachement d'une réservation à son AGENCE DE VENTE.
 *
 * `office_id` est NULLABLE, et c'est un choix métier, pas une facilité : une
 * réservation prise EN LIGNE n'a pas d'agence physique. Les réservations
 * historiques restent donc `NULL` — ce qui est EXACT, leur agence n'ayant
 * jamais été enregistrée (aucune donnée n'est inventée ni rétro-remplie).
 *
 * Index composé `(company_id, office_id)` : la ventilation par agence est la
 * requête de lecture attendue par le back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_bookings')) {
            return;
        }

        if (Schema::hasColumn('travel_bookings', 'office_id')) {
            return;
        }

        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('office_id')->nullable();

            $table->index(['company_id', 'office_id'], 'travel_bookings_company_office_idx');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_bookings')) {
            return;
        }

        if (! Schema::hasColumn('travel_bookings', 'office_id')) {
            return;
        }

        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropIndex('travel_bookings_company_office_idx');
            $table->dropColumn('office_id');
        });
    }
};
