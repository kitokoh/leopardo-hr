<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5735 (CRM-PRE) — Runner canonique des migrations CRM.
 *
 * Vérifie le comportement de `php artisan leopardo:migrate` :
 *  - réentrance (double exécution sans erreur) ;
 *  - atterrissage des tables tenant dans `shared_tenants` et des tables
 *    public dans `public` ;
 *  - cycle migrate → rollback → migrate sur une migration temporaire
 *    isolée (création, down, recréation).
 *
 * Référentiel : docs/specifications/MIGRATIONS_CRM_LARAVEL.md
 */
class LeopardoMigrateRunnerTest extends TestCase
{
    use RefreshTenantDatabase;

    private const TEMP_MIGRATION = 'database/migrations/tenant/2099_01_01_000001_5735_temp_runner_probe.php';

    protected function tearDown(): void
    {
        $this->removeTempMigration();
        parent::tearDown();
    }

    public function test_runner_is_reentrant(): void
    {
        // RefreshTenantDatabase a déjà migré public + tenant : une nouvelle
        // exécution du runner canonique doit être un no-op propre.
        $first = Artisan::call('leopardo:migrate');
        self::assertSame(0, $first, 'leopardo:migrate doit réussir sur une base déjà migrée');

        $second = Artisan::call('leopardo:migrate');
        self::assertSame(0, $second, 'seconde exécution : réentrance garantie');
    }

    public function test_tenant_tables_land_in_shared_tenants_and_public_tables_in_public(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Vérification de schéma PostgreSQL uniquement.');
        }

        // Table tenant canonique (migrations/tenant) → shared_tenants.
        $tenantSchema = DB::scalar(
            "SELECT table_schema FROM information_schema.tables WHERE table_name = 'employees'"
        );
        self::assertSame('shared_tenants', $tenantSchema);

        // Table public canonique (migrations/public) → public.
        $publicSchema = DB::scalar(
            "SELECT table_schema FROM information_schema.tables WHERE table_name = 'companies'"
        );
        self::assertSame('public', $publicSchema);
    }

    public function test_migrate_rollback_migrate_cycle_on_isolated_migration(): void
    {
        $this->writeTempMigration();

        // 1. La migration temporaire est exécutée par le runner tenant.
        $code = Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        self::assertSame(0, $code);

        $table = $this->tableSchema('crm_runner_probe');
        self::assertNotNull($table, 'la table temporaire doit exister après migrate');
        self::assertSame('shared_tenants', $table);

        // 2. Rollback d'une étape : la table temporaire disparaît.
        $code = Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/tenant',
            '--step' => 1,
            '--force' => true,
        ]);
        self::assertSame(0, $code);
        self::assertNull($this->tableSchema('crm_runner_probe'), 'down() doit dropper la table temporaire');

        // 3. Re-exécution : la table est recréée (réentrance du cycle).
        $code = Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        self::assertSame(0, $code);
        self::assertNotNull($this->tableSchema('crm_runner_probe'), 'recréation après rollback');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function tableSchema(string $table): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $schema = DB::scalar(
            'SELECT table_schema FROM information_schema.tables WHERE table_name = ?',
            [$table]
        );

        return is_string($schema) ? $schema : null;
    }

    private function writeTempMigration(): void
    {
        $content = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5735 — Migration TEMPORAIRE de test du runner (jamais mergée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_runner_probe')) {
            Schema::create('crm_runner_probe', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_runner_probe');
    }
};
PHP;

        file_put_contents(base_path(self::TEMP_MIGRATION), $content);
    }

    private function removeTempMigration(): void
    {
        $path = base_path(self::TEMP_MIGRATION);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
