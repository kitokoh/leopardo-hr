<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1933 — Migrations public/tenant hors pattern F-17 (#1595) :
 * `public_holidays`, `islamic_calendar` et `tax_rate_change_log` étaient
 * créées/altérées avec des noms NON qualifiés et des gardes
 * `Schema::hasTable()` nues qui ne voient que `current_schema()` — selon le
 * search_path (CI : shared_tenants,public / local : public,shared_tenants)
 * les tables pouvaient atterrir dans le mauvais schéma, ou la migration être
 * silencieusement sautée (garde répondant faux). Ce test verrouille :
 *   1. le placement réel des tables (base fraîche via RefreshTenantDatabase) ;
 *   2. l'idempotence des gardes F-17 (re-run direct des migrations) ;
 *   3. l'idempotence du runner (`artisan migrate` rejoué).
 */
class MigrationSchemaPlacementTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Schéma réel d'une table (tous les schémas, pas seulement le search_path).
     */
    private function tableSchema(string $table): ?string
    {
        $row = DB::selectOne(
            'SELECT t.table_schema
               FROM information_schema.tables t
              WHERE t.table_name = ?
              ORDER BY t.table_schema
              LIMIT 1',
            [$table]
        );

        return $row ? (string) $row->table_schema : null;
    }

    public function test_public_holidays_is_created_in_public_schema(): void
    {
        $this->assertSame('public', $this->tableSchema('public_holidays'));
    }

    public function test_islamic_calendar_is_created_in_public_schema(): void
    {
        $this->assertSame('public', $this->tableSchema('islamic_calendar'));
    }

    public function test_tax_rate_change_log_is_created_in_tenant_schema(): void
    {
        // Les migrations tenant tournent avec search_path=shared_tenants.
        $this->assertSame('shared_tenants', $this->tableSchema('tax_rate_change_log'));
    }

    public function test_tax_slabs_and_social_contributions_got_validation_columns(): void
    {
        foreach (['tax_slabs', 'social_contributions'] as $table) {
            $schema = $this->tableSchema($table);
            $this->assertNotNull($schema, "{$table} absente — migrations tenant non rejouées ?");

            $columns = collect(DB::select(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                [$schema, $table]
            ))->pluck('column_name')->all();

            $this->assertContains('status', $columns, "{$table}.status (workflow #1813)");
            $this->assertContains('submitted_by', $columns, "{$table}.submitted_by (workflow #1813)");
        }
    }

    public function test_migration_guards_are_idempotent_when_re_run(): void
    {
        // Re-exécution DIRECTE des up() : les gardes F-17
        // (schemaTableExists / resolveTableSchema) doivent no-op proprement.
        $publicHolidays = require base_path('database/migrations/public/2026_08_14_000002_create_public_holidays_table.php');
        $islamic = require base_path('database/migrations/public/2026_08_14_000003_create_islamic_calendar_table.php');
        $rateWorkflow = require base_path('database/migrations/tenant/2026_08_14_000001_add_rate_validation_workflow.php');
        // Issue #2326 : la contrainte UNIQUE ajoutée par 000004 doit être
        // idempotente (le garde Schema::hasTable ne couvre pas les
        // contraintes → SQLSTATE[42P07] sur re-migrate). On la rejoue deux
        // fois pour verrouiller le garde information_schema.
        $uniquePublicHolidays = require base_path('database/migrations/public/2026_08_14_000004_add_unique_public_holidays.php');

        $publicHolidays->up();
        $islamic->up();
        $rateWorkflow->up();
        $uniquePublicHolidays->up();
        $uniquePublicHolidays->up();

        // Toujours au même endroit, toujours une seule fois.
        $this->assertSame('public', $this->tableSchema('public_holidays'));
        $this->assertSame('public', $this->tableSchema('islamic_calendar'));
        $this->assertSame('shared_tenants', $this->tableSchema('tax_rate_change_log'));
        $this->assertSame(1, (int) DB::table('information_schema.tables')
            ->where('table_name', 'public_holidays')
            ->count());
    }

    public function test_public_holidays_unique_constraint_is_idempotent_when_re_run(): void
    {
        // Issue #2326 : `2026_08_14_000004` (UNIQUE country/year/date/company)
        // rejouait l'ajout de contrainte sans garde → 42P07 sur le second
        // `artisan migrate` (la garde Schema::hasTable ne couvre pas les
        // contraintes). Le up() doit être no-op quand la contrainte existe.
        $migration = require base_path(
            'database/migrations/public/2026_08_14_000004_add_unique_public_holidays.php'
        );

        // Premier passage : la contrainte est créée (base fraîche).
        $migration->up();

        $this->assertSame(
            1,
            (int) DB::table('information_schema.table_constraints')
                ->where('constraint_name', 'public_holidays_country_year_date_company_unique')
                ->where('table_name', 'public_holidays')
                ->count(),
            'La contrainte UNIQUE doit exister après le premier up()'
        );

        // Second passage : no-op, aucune exception 42P07.
        $migration->up();

        $this->assertSame(
            1,
            (int) DB::table('information_schema.table_constraints')
                ->where('constraint_name', 'public_holidays_country_year_date_company_unique')
                ->where('table_name', 'public_holidays')
                ->count(),
            'La contrainte UNIQUE ne doit pas être dupliquée après re-run'
        );
    }

    public function test_artisan_migrate_is_idempotent(): void
    {
        // PHPStan Strict : artisan() retourne PendingCommand|int — annotation
        // @var + assertExitCode (pattern BiometricPurgeExpiredTest).
        /** @var PendingCommand $command */
        $command = $this->artisan('migrate', ['--path' => 'database/migrations/public']);
        $command->assertExitCode(0);

        /** @var PendingCommand $command */
        $command = $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $command->assertExitCode(0);
    }
}
