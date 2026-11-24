<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 (évaluation leopardo-hr) — temps de correspondance minimum PAR GARE.
 *
 * Avant ce lot, le délai minimal entre deux trajets d'une correspondance était
 * une constante PHP (`ConnectionSearchAction::MIN_CONNECTION_MINUTES = 45`) et
 * un littéral `45` dans `TravelConnectionController`. Or ce délai est une
 * donnée d'exploitation, pas une propriété du code : une gare de brousse où il
 * faut traverser la ville à pied n'a pas le même besoin qu'un terminal
 * moderne où l'on change de quai. Le figer en dur obligeait à redéployer pour
 * l'ajuster, et interdisait deux gares du même réseau d'avoir des règles
 * différentes.
 *
 * `min_connection_minutes` NULL = « pas de surcharge » : la gare hérite du
 * défaut du tenant (`config('travel.connections.default_min_minutes')`,
 * surchargeable par `TRAVEL_MIN_CONNECTION_MINUTES`). Une valeur renseignée
 * prime sur ce défaut.
 *
 * Additive et réentrante (garde `schemaHasColumn`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_stations')) {
            return;
        }

        if (! schemaHasColumn('travel_stations', 'min_connection_minutes')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->unsignedSmallInteger('min_connection_minutes')->nullable()->after('timezone');
            });

            // Le domaine est borné : sous 15 min la correspondance n'est pas
            // physiquement tenable, au-delà de 240 min ce n'est plus une
            // correspondance mais une escale. La contrainte est posée en base
            // pour qu'aucun chemin d'écriture (API, import, console) ne puisse
            // la contourner.
            DB::statement(
                'ALTER TABLE travel_stations ADD CONSTRAINT travel_stations_min_connection_check '
                .'CHECK (min_connection_minutes IS NULL OR (min_connection_minutes >= 15 AND min_connection_minutes <= 240))'
            );

            DB::statement(
                'COMMENT ON COLUMN travel_stations.min_connection_minutes IS '
                ."'Delai minimal de correspondance impose par la gare, en minutes (NULL = defaut du tenant) - M5.'"
            );
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_stations') || ! schemaHasColumn('travel_stations', 'min_connection_minutes')) {
            return;
        }

        DB::statement('ALTER TABLE travel_stations DROP CONSTRAINT IF EXISTS travel_stations_min_connection_check');

        Schema::table('travel_stations', function (Blueprint $table): void {
            $table->dropColumn('min_connection_minutes');
        });
    }
};
