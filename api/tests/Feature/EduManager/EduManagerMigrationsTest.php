<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5818 (EDU-002) — migrations EduManager : campus, élèves,
 * responsables légaux et relations autorisées.
 *
 * Verrouille :
 *   1. le placement des 4 tables dans le schéma tenant (shared_tenants) ;
 *   2. `company_id` NON nullable (isolation BelongsToCompany) ;
 *   3. les contraintes CHECK nommées (status / relationship_code) ;
 *   4. les UNIQUE composites par tenant (code campus, numéro élève) ;
 *   5. l'impossibilité STRUCTURELLE des relations cross-tenant (FK
 *      composites student_id/guardian_id + company_id) ;
 *   6. l'unicité de la paire (student_id, guardian_id) par tenant ;
 *   7. l'idempotence des gardes F-17 (up() rejoué) et le cycle down()/up().
 */
class EduManagerMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @var list<string> */
    private const TABLES = [
        'edu_campuses',
        'edu_students',
        'edu_guardians',
        'edu_student_guardians',
    ];

    /** @var list<string> */
    private const MIGRATIONS = [
        '2026_08_30_000101_5818_create_edu_campuses_table',
        '2026_08_30_000102_5818_create_edu_students_table',
        '2026_08_30_000103_5818_create_edu_guardians_table',
        '2026_08_30_000104_5818_create_edu_student_guardians_table',
    ];

    private function newCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

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

    /**
     * @return Migration
     */
    private function migration(string $basename): Migration
    {
        $path = database_path("migrations/tenant/{$basename}.php");
        $this->assertFileExists($path);

        $migration = require $path;

        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    public function test_tables_are_created_in_tenant_schema(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table), "{$table} doit être créée dans shared_tenants");
        }
    }

    public function test_company_id_is_not_nullable_on_all_tables(): void
    {
        foreach (self::TABLES as $table) {
            $row = DB::selectOne(
                'SELECT is_nullable
                   FROM information_schema.columns
                  WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                ['shared_tenants', $table, 'company_id']
            );

            $this->assertNotNull($row, "{$table}.company_id absente");
            $this->assertSame('NO', (string) $row->is_nullable, "{$table}.company_id doit être NON nullable");
        }
    }

    public function test_campus_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_campuses')->insert([
                'company_id' => $this->newCompany()->id,
                'code' => 'MAIN',
                'name' => 'Campus invalide',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_student_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_students')->insert([
                'company_id' => $this->newCompany()->id,
                'student_number' => 'S-001',
                'display_name' => 'Élève invalide',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_guardian_relationship_check_rejects_unknown_code(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_guardians')->insert([
                'company_id' => $this->newCompany()->id,
                'relationship_code' => 'uncle-unknown',
            ]);
        });
    }

    public function test_student_guardian_relationship_check_rejects_unknown_code(): void
    {
        /** @var Company $company */
        $company = $this->newCompany();
        $studentId = $this->createStudent($company->id, 'S-001');
        $guardianId = $this->createGuardian($company->id, 'guardien@exemple.test');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company, $studentId, $guardianId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $company->id,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
                'relationship_code' => 'cousin-unknown',
            ]);
        });
    }

    public function test_campus_code_is_unique_per_tenant(): void
    {
        $company = $this->newCompany();
        $otherCompany = $this->newCompany();

        DB::table('edu_campuses')->insert([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Campus A',
        ]);

        // Même code sur un AUTRE tenant : autorisé.
        DB::table('edu_campuses')->insert([
            'company_id' => $otherCompany->id,
            'code' => 'MAIN',
            'name' => 'Campus B',
        ]);

        // Même code sur le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company): void {
            DB::table('edu_campuses')->insert([
                'company_id' => $company->id,
                'code' => 'MAIN',
                'name' => 'Campus doublon',
            ]);
        });
    }

    public function test_student_number_is_unique_per_tenant(): void
    {
        $company = $this->newCompany();

        DB::table('edu_students')->insert([
            'company_id' => $company->id,
            'student_number' => 'S-001',
            'display_name' => 'Élève A',
        ]);

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company): void {
            DB::table('edu_students')->insert([
                'company_id' => $company->id,
                'student_number' => 'S-001',
                'display_name' => 'Élève doublon',
            ]);
        });
    }

    public function test_cross_tenant_student_guardian_link_is_rejected_by_database(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();
        $studentAId = $this->createStudent($companyA->id, 'S-A1');

        // Gardien du tenant B lié à un élève du tenant A : la FK composite
        // (guardian_id, company_id) doit rejeter l'insertion.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($companyB, $studentAId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $companyB->id,
                'student_id' => $studentAId,
                'guardian_id' => $this->createGuardian($companyB->id, 'gardienB@exemple.test'),
            ]);
        });
    }

    public function test_duplicate_student_guardian_link_is_rejected(): void
    {
        /** @var Company $company */
        $company = $this->newCompany();
        $studentId = $this->createStudent($company->id, 'S-001');
        $guardianId = $this->createGuardian($company->id, 'gardien@exemple.test');

        DB::table('edu_student_guardians')->insert([
            'company_id' => $company->id,
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
        ]);

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company, $studentId, $guardianId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $company->id,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
            ]);
        });
    }

    public function test_up_is_idempotent(): void
    {
        foreach (self::MIGRATIONS as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'up'); // rejouer up() sans erreur (gardes F-17)
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table));
        }
    }

    public function test_down_up_cycle_is_clean(): void
    {
        // down() dans l'ordre inverse des dépendances, puis up() complet.
        foreach (array_reverse(self::MIGRATIONS) as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'down');
        }

        foreach (self::TABLES as $table) {
            $this->assertNull($this->tableSchema($table), "{$table} doit être supprimée après down()");
        }

        foreach (self::MIGRATIONS as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'up');
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table));
        }
    }

    private function createStudent(string $companyId, string $studentNumber): int
    {
        return (int) DB::table('edu_students')->insertGetId([
            'company_id' => $companyId,
            'student_number' => $studentNumber,
            'display_name' => 'Élève test',
        ]);
    }

    private function createGuardian(string $companyId, string $contactReference): int
    {
        return (int) DB::table('edu_guardians')->insertGetId([
            'company_id' => $companyId,
            'contact_reference' => $contactReference,
        ]);
    }

    private function callMigrationMethod(Migration $migration, string $method): void
    {
        // Les migrations du repo sont des classes anonymes `return new class extends Migration` :
        // invocation réflexive pour le cycle up/down de test (les méthodes ne sont pas
        // déclarées sur le type de base).
        $reflection = new \ReflectionMethod($migration, $method);
        $reflection->invoke($migration);
    }
}

