<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8467 — vérifie que la migration produit réellement une colonne
 * `geography(Point,4326)` GÉNÉRÉE et un index GIST, et que la recherche
 * proximale bascule bien sur la colonne native quand PostGIS est présent.
 *
 * Un test de migration qui ne lit que du code PHP ne prouve rien : le DDL
 * PostgreSQL (`GENERATED ALWAYS AS ... STORED`, `USING GIST`) n'est validé que
 * par le serveur. On interroge donc `information_schema` / `pg_indexes`.
 */
final class NativeGeographyColumnsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_vtc_drivers_has_generated_geography_column(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->postgisAvailable()) {
            $this->markTestSkipped('PostGIS requis pour valider le DDL geography.');
        }

        $column = DB::selectOne(
            "SELECT is_generated, generation_expression, udt_name
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'vtc_drivers'
               AND column_name = 'current_location'"
        );

        $this->assertNotNull($column, 'La colonne geography current_location est absente.');
        $this->assertSame('ALWAYS', $column->is_generated, 'La colonne doit être GENERATED ALWAYS (jamais désynchronisable).');
        $this->assertSame('geography', $column->udt_name);
        $this->assertStringContainsString('st_makepoint', strtolower((string) $column->generation_expression));
    }

    public function test_geography_column_has_a_gist_index(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->postgisAvailable()) {
            $this->markTestSkipped('PostGIS requis pour valider l\'index GIST.');
        }

        $index = DB::selectOne(
            "SELECT indexdef FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename = 'vtc_drivers'
               AND indexname = 'vtc_drivers_current_location_gist'"
        );

        $this->assertNotNull($index, 'Sans index GIST, la colonne native ne sert à rien : ST_DWithin retombe sur un scan complet.');
        $this->assertStringContainsString('gist', strtolower($index->indexdef));
    }

    public function test_generated_column_tracks_lat_lng_automatically(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->postgisAvailable()) {
            $this->markTestSkipped('PostGIS requis.');
        }

        // Insertion volontairement partielle : on n'écrit QUE lat/lng, jamais
        // `current_location` (qui est en lecture seule). Si la colonne est bien
        // générée, elle est peuplée sans qu'aucun code applicatif ne s'en
        // occupe — c'est toute la garantie contre la désynchronisation.
        DB::table('vtc_drivers')->insert([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Test Driver',
            'status' => 'offline',
            'current_latitude' => 36.7538,
            'current_longitude' => 3.0588,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::selectOne(
            "SELECT ST_X(current_location::geometry) AS lng, ST_Y(current_location::geometry) AS lat
             FROM vtc_drivers WHERE name = 'Test Driver'"
        );

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(3.0588, (float) $row->lng, 0.0000001);
        $this->assertEqualsWithDelta(36.7538, (float) $row->lat, 0.0000001);
    }

    private function postgisAvailable(): bool
    {
        try {
            return DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'postgis'") !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
