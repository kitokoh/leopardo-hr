<?php

declare(strict_types=1);

namespace Tests\Feature\DataModel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1772 — Dérive prod : `public.companies.features`/`metadata` absents
 * alors que la migration d'origine est enregistrée comme exécutée.
 *
 * Vérifie que la migration de réparation
 * `2026_08_14_000001_ensure_companies_features_metadata_columns` :
 *  1. re-crée la colonne `features` quand elle a été perdue (scénario prod) ;
 *  2. débloque `GET /api/v1/employees/{id}` qui eager-load
 *     `company:id,name,language,timezone,currency,features` (le 500 prod).
 */
class EnsureCompaniesFeaturesMetadataColumnsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_migration_repairs_missing_features_column(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        // Simule la dérive production : colonne supprimée alors que la
        // migration d'origine est enregistrée comme exécutée.
        DB::statement('ALTER TABLE public.companies DROP COLUMN IF EXISTS features');
        DB::statement('ALTER TABLE public.companies DROP COLUMN IF EXISTS metadata');

        self::assertFalse(Schema::hasColumn('companies', 'features'));
        self::assertFalse(Schema::hasColumn('companies', 'metadata'));

        // Rejoue UNIQUEMENT la migration de réparation (comme `migrate` le
        // fera sur un environnement dérivé).
        $migration = require database_path(
            'migrations/public/2026_08_14_000001_ensure_companies_features_metadata_columns.php'
        );
        $migration->up();

        self::assertTrue(Schema::hasColumn('companies', 'features'));
        self::assertTrue(Schema::hasColumn('companies', 'metadata'));

        // L'employé existe toujours et la colonne est de nouveau peuplée avec
        // le défaut attendu.
        $fresh = $company->fresh();
        self::assertNotNull($fresh);
        self::assertSame([], $fresh->features);
    }

    public function test_employee_show_returns_200_after_repair(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // Dérive prod + réparation via la migration.
        DB::statement('ALTER TABLE public.companies DROP COLUMN IF EXISTS features');
        DB::statement('ALTER TABLE public.companies DROP COLUMN IF EXISTS metadata');
        $migration = require database_path(
            'migrations/public/2026_08_14_000001_ensure_companies_features_metadata_columns.php'
        );
        $migration->up();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id);
    }

    public function test_migration_is_idempotent(): void
    {
        Company::factory()->create();

        $migration = require database_path(
            'migrations/public/2026_08_14_000001_ensure_companies_features_metadata_columns.php'
        );
        $migration->up();
        $migration->up();

        self::assertTrue(Schema::hasColumn('companies', 'features'));
        self::assertTrue(Schema::hasColumn('companies', 'metadata'));
        self::assertTrue(Schema::hasIndex('companies', 'companies_features_gin'));
    }
}
