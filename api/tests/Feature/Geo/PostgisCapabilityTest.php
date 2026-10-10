<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GEO-01 (#8350, BC-33 GEO) — extension PostGIS et diagnostic.
 *
 * Sur PostgreSQL (CI PG16), la migration publique #8350 installe
 * l'extension : elle doit être présente, rejouable sans erreur, et la
 * commande `geo:check-postgis` doit la détecter. Hors pgsql (SQLite local),
 * les tests sont skippés — le mode dégradé Haversine est couvert en GEO-03.
 */
class PostgisCapabilityTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_postgis_extension_is_installed(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostGIS n\'est testé que sur PostgreSQL.');
        }

        $row = DB::selectOne("SELECT extname FROM pg_extension WHERE extname = 'postgis'");

        self::assertNotNull($row, 'L\'extension PostGIS doit être installée par la migration #8350.');
    }

    public function test_postgis_extension_migration_is_replayable(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostGIS n\'est testé que sur PostgreSQL.');
        }

        $migration = include base_path('database/migrations/public/2026_10_10_000001_8350_enable_postgis_extension.php');

        self::assertInstanceOf(Migration::class, $migration);

        // @phpstan-ignore-next-line method.notFound (classe de migration anonyme — up() défini à l'inclusion)
        $migration->up();
        // @phpstan-ignore-next-line method.notFound (classe de migration anonyme — up() défini à l'inclusion)
        $migration->up();

        $row = DB::selectOne("SELECT extname FROM pg_extension WHERE extname = 'postgis'");

        self::assertNotNull($row);
    }

    public function test_check_postgis_command_succeeds(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostGIS n\'est testé que sur PostgreSQL.');
        }

        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('geo:check-postgis')->assertSuccessful();
    }
}
