<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Policies\EduClassPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5819 (EDU-003) — classes : modèle, policy et invariants de schéma.
 *
 * Verrouille :
 *   1. la création d'une classe rattachée à une année scolaire (relation
 *      `academicYear()`) et l'accès gestionnaire (policy directe) ;
 *   2. la FK composite (academic_year_id, company_id) → edu_academic_years
 *      (id, company_id) : une classe pointant l'année d'un AUTRE tenant est
 *      STRUCTURELLEMENT impossible (violation FK en base) ;
 *   3. l'unicité (company_id, academic_year_id, name) — même nom autorisé
 *      pour une autre année ou un autre tenant ;
 *   4. le CHECK `status` et l'archivage logique (historique conservé) ;
 *   5. l'isolation cross-tenant de la policy (→ 403/404 en API, EDU-006).
 */
class EduClassTest extends TestCase
{
    use RefreshTenantDatabase;

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

    public function test_manager_can_create_class_for_academic_year(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');

        /** @var EduClass $class */
        $class = EduClass::query()->create([
            'company_id' => $this->company->id,
            'academic_year_id' => $year->id,
            'name' => '6AP',
            'grade_level' => '6e',
            'capacity' => 30,
        ]);

        $policy = app(EduClassPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->view($this->manager, $class));
        $this->assertTrue($policy->update($this->manager, $class));

        // Relation Eloquent typée vers l'année scolaire.
        $this->assertTrue($class->academicYear->is($year));

        $this->assertDatabaseHas('edu_classes', [
            'company_id' => $this->company->id,
            'academic_year_id' => $year->id,
            'name' => '6AP',
            'grade_level' => '6e',
            'capacity' => 30,
            'status' => 'active',
        ]);
    }

    public function test_class_grade_level_and_capacity_are_optional(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');

        /** @var EduClass $class */
        $class = EduClass::query()->create([
            'company_id' => $this->company->id,
            'academic_year_id' => $year->id,
            'name' => '6AP',
        ]);

        $this->assertNull($class->grade_level);
        $this->assertNull($class->capacity);
    }

    public function test_plain_employee_is_not_authorized(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $class = $this->class($this->company, $this->academicYear($this->company, '2025-2026'), '6AP');

        $policy = app(EduClassPolicy::class);

        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->view($employee, $class));
    }

    public function test_manager_never_sees_other_tenant_class(): void
    {
        $own = $this->class($this->company, $this->academicYear($this->company, '2025-2026'), '6AP');
        $otherTenant = $this->class($this->otherCompany, $this->academicYear($this->otherCompany, '2024-2025'), '6AP');

        $policy = app(EduClassPolicy::class);

        // Un gestionnaire voit les classes de SON tenant…
        $this->assertTrue($policy->view($this->manager, $own));
        // … et JAMAIS celles d'un autre tenant (→ 403/404 en API, EDU-006).
        $this->assertFalse($policy->view($this->manager, $otherTenant));
    }

    public function test_cross_tenant_academic_year_reference_is_rejected_by_database(): void
    {
        // Classe déclarée chez le tenant A mais rattachée à l'année du tenant B :
        // la FK composite (academic_year_id, company_id) doit rejeter l'insertion.
        $otherTenantYear = $this->academicYear($this->otherCompany, '2024-2025');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($otherTenantYear): void {
            DB::table('edu_classes')->insert([
                'company_id' => $this->company->id,
                'academic_year_id' => $otherTenantYear->id,
                'name' => '6AP',
            ]);
        });
    }

    public function test_class_name_is_unique_per_year_and_tenant(): void
    {
        $yearA = $this->academicYear($this->company, '2025-2026');
        $yearB = $this->academicYear($this->company, '2026-2027');

        DB::table('edu_classes')->insert([
            'company_id' => $this->company->id,
            'academic_year_id' => $yearA->id,
            'name' => '6AP',
        ]);

        // Même nom pour une AUTRE année du même tenant : autorisé.
        DB::table('edu_classes')->insert([
            'company_id' => $this->company->id,
            'academic_year_id' => $yearB->id,
            'name' => '6AP',
        ]);

        // Même nom chez un AUTRE tenant (chacun avec sa propre année) : autorisé.
        $otherYear = $this->academicYear($this->otherCompany, '2025-2026');
        DB::table('edu_classes')->insert([
            'company_id' => $this->otherCompany->id,
            'academic_year_id' => $otherYear->id,
            'name' => '6AP',
        ]);

        // Même nom + même année + même tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($yearA): void {
            DB::table('edu_classes')->insert([
                'company_id' => $this->company->id,
                'academic_year_id' => $yearA->id,
                'name' => '6AP',
            ]);
        });
    }

    public function test_status_check_rejects_unknown_status(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($year): void {
            DB::table('edu_classes')->insert([
                'company_id' => $this->company->id,
                'academic_year_id' => $year->id,
                'name' => '6AP',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_archived_class_keeps_history(): void
    {
        $class = $this->class($this->company, $this->academicYear($this->company, '2025-2026'), '6AP');

        // Archive logique : jamais de DELETE dur (historique conservé).
        $class->update(['status' => EduClass::STATUS_ARCHIVED]);

        $this->assertSame(1, DB::table('edu_classes')->where('company_id', $this->company->id)->count());
        $this->assertDatabaseHas('edu_classes', [
            'company_id' => $this->company->id,
            'name' => '6AP',
            'status' => 'archived',
        ]);
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

    private function class(Company $company, EduAcademicYear $year, string $name): EduClass
    {
        /** @var EduClass $class */
        $class = EduClass::query()->create([
            'company_id' => $company->id,
            'academic_year_id' => $year->id,
            'name' => $name,
        ]);

        return $class;
    }
}
