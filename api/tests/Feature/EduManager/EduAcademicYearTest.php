<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Policies\EduAcademicYearPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5819 (EDU-003) — années scolaires : modèle, policy et schéma.
 *
 * Verrouille :
 *   1. la création d'une année scolaire (modèle) et l'accès gestionnaire
 *      (principal/rh/manager, policy directe) ;
 *   2. le CHECK `start_date < end_date` — une période incohérente est
 *      rejetée en base (exception ; en API EDU-006 → 422 côté validation) ;
 *   3. l'unicité du nom PAR TENANT (même nom autorisé chez un autre tenant) ;
 *   4. l'isolation cross-tenant (policy view refusée → 403/404 en API) ;
 *   5. l'archivage logique : statut `archived` conserve la ligne (pas de
 *      DELETE dur — historique préservé) ;
 *   6. le schéma tenant : placement shared_tenants, `company_id` NON
 *      nullable, CHECK status, idempotence et cycle down()/up() des 5
 *      migrations #5819.
 */
class EduAcademicYearTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @var list<string> */
    private const TABLES = [
        'edu_academic_years',
        'edu_classes',
        'edu_subjects',
        'edu_teachers',
        'edu_teacher_subjects',
    ];

    /** @var list<string> */
    private const MIGRATIONS = [
        // Toutes les migrations EduManager, dans l'ordre chronologique des
        // préfixes — le down() s'exécute en ordre inverse (dépendances d'abord).
        '2026_08_30_000101_5818_create_edu_campuses_table',
        '2026_08_30_000102_5818_create_edu_students_table',
        '2026_08_30_000103_5818_create_edu_guardians_table',
        '2026_08_30_000104_5818_create_edu_student_guardians_table',
        '2026_08_30_000201_5819_create_edu_academic_years_table',
        '2026_08_30_000202_5819_create_edu_classes_table',
        '2026_08_30_000203_5819_create_edu_subjects_table',
        '2026_08_30_000204_5819_create_edu_teachers_table',
        '2026_08_30_000205_5819_create_edu_teacher_subjects_table',
        '2026_08_30_000301_5820_create_edu_admissions_table',
        '2026_08_30_000401_5821_create_edu_attendance_records_table',
        '2026_08_30_000402_5821_create_edu_attendance_corrections_table',
        '2026_08_30_000501_5822_create_edu_timetable_slots_table',
        '2026_08_30_000601_5823_create_edu_assessments_table',
        '2026_08_30_000602_5823_create_edu_grades_table',
        '2026_08_30_000603_5823_create_edu_grade_versions_table',
        '2026_08_30_000701_5824_create_edu_report_cards_table',
    ];

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);
        $this->manager = $manager;
    }

    public function test_manager_can_create_academic_year(): void
    {
        $policy = app(EduAcademicYearPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));

        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $this->company->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        $this->assertTrue($policy->view($this->manager, $year));
        $this->assertTrue($policy->update($this->manager, $year));
        $this->assertTrue($policy->delete($this->manager, $year));

        $this->assertDatabaseHas('edu_academic_years', [
            'company_id' => $this->company->id,
            'name' => '2025-2026',
            'status' => 'active',
        ]);
        $this->assertSame(EduAcademicYear::STATUS_ACTIVE, $year->status);
        // Casts typés : dates en Carbon.
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $year->start_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $year->end_date);
    }

    public function test_plain_employee_is_not_authorized(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $year = $this->academicYear($this->company, '2025-2026');

        $policy = app(EduAcademicYearPolicy::class);

        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->view($employee, $year));
    }

    public function test_manager_never_sees_other_tenant_academic_year(): void
    {
        $own = $this->academicYear($this->company, '2025-2026');
        $otherTenant = $this->academicYear($this->otherCompany, '2024-2025');

        $policy = app(EduAcademicYearPolicy::class);

        // Un gestionnaire voit les années de SON tenant…
        $this->assertTrue($policy->view($this->manager, $own));
        // … et JAMAIS celles d'un autre tenant (→ 403/404 en API, EDU-006).
        $this->assertFalse($policy->view($this->manager, $otherTenant));
        $this->assertFalse($policy->update($this->manager, $otherTenant));
        $this->assertFalse($policy->delete($this->manager, $otherTenant));
    }

    public function test_incoherent_period_is_rejected(): void
    {
        // start_date >= end_date : CHECK edu_academic_years_period_check.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_academic_years')->insert([
                'company_id' => $this->company->id,
                'name' => '2025-2026 inversée',
                'start_date' => '2026-06-30',
                'end_date' => '2025-09-01',
            ]);
        });
    }

    public function test_academic_year_name_is_unique_per_tenant(): void
    {
        DB::table('edu_academic_years')->insert([
            'company_id' => $this->company->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        // Même nom sur un AUTRE tenant : autorisé.
        DB::table('edu_academic_years')->insert([
            'company_id' => $this->otherCompany->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        // Même nom sur le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_academic_years')->insert([
                'company_id' => $this->company->id,
                'name' => '2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-06-30',
            ]);
        });
    }

    public function test_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_academic_years')->insert([
                'company_id' => $this->company->id,
                'name' => '2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-06-30',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_archived_academic_year_keeps_history(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');

        // Archive logique : jamais de DELETE dur (historique conservé).
        $year->update(['status' => EduAcademicYear::STATUS_ARCHIVED]);

        $this->assertSame(1, DB::table('edu_academic_years')->where('company_id', $this->company->id)->count());
        $this->assertDatabaseHas('edu_academic_years', [
            'company_id' => $this->company->id,
            'name' => '2025-2026',
            'status' => 'archived',
        ]);

        // Même archivée, la ligne reste visible des gestionnaires du tenant.
        $this->assertTrue(app(EduAcademicYearPolicy::class)->view($this->manager, $year->refresh()));
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
        // Les migrations d'autres issues EduManager (EDU-004/005, ex.
        // edu_admissions.academic_year_id) portent des FK composites vers
        // les tables #5819 : PostgreSQL interdit de dropper un parent encore
        // référencé. Les tables dépendantes sont donc supprimées d'abord
        // (résolution dynamique — aucun nom d'issue voisine en dur) ; le
        // cycle complet se déroule dans la transaction du test, et up()
        // recrée les tables #5819 via leurs propres migrations.
        // down() dans l'ordre inverse des dépendances (toutes les migrations EduManager),
        // puis up() complet — les FK composites imposent l'ordre inverse des préfixes.
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

    /**
     * Supprime (dans la transaction de test) les tables du schéma tenant qui
     * portent une FK vers l'une des tables données — y compris en cascade de
     * dépendances (table dépendante d'une table dépendante).
     *
     * @param list<string> $tables
     */
    private function dropTablesReferencing(array $tables): void
    {
        $dropped = $tables;

        do {
            $dependents = [];
            foreach ($dropped as $table) {
                $rows = DB::select(
                    'SELECT DISTINCT tc.table_name
                       FROM information_schema.table_constraints tc
                       JOIN information_schema.key_column_usage kcu
                         ON kcu.constraint_name = tc.constraint_name
                        AND kcu.table_schema = tc.table_schema
                       JOIN information_schema.constraint_column_usage ccu
                         ON ccu.constraint_name = tc.constraint_name
                        AND ccu.table_schema = tc.table_schema
                      WHERE tc.constraint_type = ?
                        AND tc.table_schema = ?
                        AND ccu.table_name = ?',
                    ['FOREIGN KEY', 'shared_tenants', $table]
                );
                foreach ($rows as $row) {
                    $dependents[] = (string) $row->table_name;
                }
            }

            $dependents = array_values(array_unique(array_diff($dependents, $dropped)));
            foreach ($dependents as $dependent) {
                Schema::dropIfExists($dependent);
            }
            $dropped = array_merge($dropped, $dependents);
        } while ($dependents !== []);
    }

    private function academicYear(Company $company, string $name): EduAcademicYear
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        return $year;
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

    private function callMigrationMethod(Migration $migration, string $method): void
    {
        // Les migrations du repo sont des classes anonymes `return new class extends Migration` :
        // invocation réflexive pour le cycle up/down de test (les méthodes ne sont pas
        // déclarées sur le type de base).
        $reflection = new \ReflectionMethod($migration, $method);
        $reflection->invoke($migration);
    }
}
